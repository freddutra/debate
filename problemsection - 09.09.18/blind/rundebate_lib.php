<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Adds to the course a section where the teacher can submit a problem to groups of students
 * and give them various collaboration tools to work together on a solution.
 *
 * @package   local_problemsection
 * @copyright 2016 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : lib.php
 * Library functions
 */

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->dirroot/course/lib.php");
require_once($CFG->dirroot.'/group/lib.php');

function create_forum($courseid, $forumname, $debate){
    global $COURSE, $DB;

    $getmoduledata = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid, 'problemid'=>$debate));
    $returnsessionid = $DB->get_record('course_sections', array('id'=>$getmoduledata->sessionid));

    $forum = new stdClass();
    $forum->course = $courseid;
    $forum->type = "blog";    
    $forum->name = $forumname . " (" . $getmoduledata->debatename . ")";
    $forum->intro = "";
    $forum->timemodified = time();
    $forum->id = $DB->insert_record("forum", $forum);
    
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = 9; // Module id (9 = forum)
    $mod->instance = $forum->id;
    $mod->section = 0;
    $mod->added = time();
    $mod->id = add_course_module($mod);

    $sectionid = course_add_cm_to_section($courseid, $mod->id, $returnsessionid->section);
    
    $DB->set_field("course_modules", "section", $sectionid, array("id" => $mod->id));
    $DB->set_field("course_modules", "groupmode", 1, array("id" => $mod->id));
    rebuild_course_cache($courseid);

    return $forum; // Return array (will be used in topics)
}

function create_debate_groups($courseid, $debate)
{
    global $DB;

    try{
        $getmodulestatusid = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid, 'problemid'=>$debate));
        //$courseidhack = $courseid + 1;
        $courseidhack = $courseid;
    
        // IMPORTANT
        // load from DB
        $grouplim; // Limite de alunos / grupo
        if($getmodulestatusid->studentspergroup > 0){$grouplim = $getmodulestatusid->studentspergroup;}
            else{$grouplim = 4;}
    
        $hardsplit = 2; // 50%
    
        $avaliablestudent = array();
        $context = context_course::instance($courseid);
        $students = $DB->get_records("user_enrolments", array('enrolid'=>$courseidhack));
    
        $students = array();
        foreach($DB->get_records("enrol", array('courseid'=>$courseid)) as $enrol){
            $enrolments = $DB->get_records("user_enrolments", array('enrolid'=>$enrol->id));
            foreach($enrolments as $student){
                //$coursecontext = context_module::instance($courseid);
                if (!has_capability('mod/folder:managefiles', $context, $student->userid, false)) {
                //if (!has_capability('mod/folder:managefiles', $coursecontext, $student->userid, false)) {
                    $avaliablestudent[] = array('userid'=>$student->userid);
                }
            }
        }
        
        // Kill script if lim > students
        $newgroupscount = count($avaliablestudent)/$grouplim;
        if(
            (count($avaliablestudent) < $grouplim) &&
            (is_int($newgroupscount) == true)
        ){ 
            header("Location: ../manage.php?id=$courseid&action=11&psid=");
            //exit(); 
        }
        
        // Get initial groups
        $selectusersgroups = array();
        $startgroups = $DB->get_records("groups", array('courseid'=>$courseid));
        foreach($startgroups as $startgroup){
            if(preg_match('/\[DI]\b/', $startgroup->name)){
                // Check who is in which group
                $returnuserofgroups = $DB->get_records("groups_members", array('groupid'=>$startgroup->id));
                $data = array();
                foreach($returnuserofgroups as $returnuserofgroup){
                    $data[$returnuserofgroup->userid] = array('userid'=>$returnuserofgroup->userid, 'groupid'=>$startgroup->id, 'groupname'=>groups_get_group_name($startgroup->id));
                }
                $selectusersgroups[$startgroup->id] = $data;
            }
        }
        // OK
        
        // Split subgroups
        $timestamp = time(); // now
        $freshcreatedgroupsid = array(); // group ids
        $groupsequence = "";
    
        if(count($avaliablestudent) >= $grouplim) {
            
            $groupnameformat = "Grupo @";
            
            // Cria grupo
            if(is_int($newgroupscount) == true){
                for($i = 0; $newgroupscount > $i; $i++){
                    $currentgroupname = str_replace('@', $i+1, $groupnameformat); // string de formatação
                    
                    $groupprefix = "_dc";
                    $groupmagicnumber = 0;
                    
                    if($i % 2 == 0){$currentgroupname = $currentgroupname . " (argumento positivo)"; $groupmagicnumber = 2;}
                    else{$currentgroupname = $currentgroupname . " (argumento negativo)"; $groupmagicnumber = 1;}
                        
                    $stringconcat = $groupprefix.'['.$groupmagicnumber.']';
                    $groupuniqueid = uniqid($stringconcat, true);
                    
                    $data = new stdClass();
                    $data->courseid = $courseid;
                    $data->name = $currentgroupname;
                    $data->idnumber = $groupuniqueid;
                    $data->id = groups_create_group($data);
    
                    if($i == 0){$groupsequence = $groupsequence  . $data->id;}
                    else{$groupsequence = $groupsequence . ',' . $data->id;}
    
                    $freshcreatedgroupsid[] = $data->id;
                    $freshcreatedgroupsid[] = $data;
                }
            }
        }
        
        print_r($freshcreatedgroupsid);
        echo $groupsequence;
        $DB->update_record('local_problemsection_status', array('id'=>$getmodulestatusid->id, 'sequence'=>$groupsequence));
    
        //echo "<pre>";
        // Insert students in groups
        $p = array();
        foreach($freshcreatedgroupsid as $newgropid){
            //print_r($newgropid);
            //$returngrouprecord = $DB->get_record('groups', array('id'=>$newgropid->id));
            //print_r($returngrouprecord);
            
            @$getfirstsectionidnumber = explode(']', $newgropid->idnumber);
            $getmagicalnumber = explode('[', $getfirstsectionidnumber[0]);
            
            @$numberusersingroup = $DB->count_records('groups_members', array("groupid"=>$newgropid->id));
            //if($numberusersingroup == 0){$numberusersingroup = $numberusersingroup + 1;}
    
            foreach($selectusersgroups as $possiblestudents){
                foreach($possiblestudents as $avaliablestudent){
                    $user = $DB->get_record('user', array('id'=>$avaliablestudent["userid"]));
                    if(($numberusersingroup < $grouplim) && (!in_array($user->id, $p))){
                        $group = @groups_get_group($newgropid, 'id, courseid', MUST_EXIST);
                        $p[] = $user->id;
                        @groups_add_member($group, $user);
                        $numberusersingroup++;
                    }
                }
            }
        }
    
        return $freshcreatedgroupsid;
    }
    catch(\Exception $e){print_r($e);}
}

function create_debate_topics($courseid, $forum, $newforuns){
    global $COURSE, $DB;

    // apos divisão dos grupos menores. PRECISA DO NUMID
    $getgroupsnewtopic = $DB->get_records('groups', array('courseid'=>$courseid));
    //$countgrouprecords = -1;
    $countgrouprecords = 1;
    foreach($newforuns as $getgroupnewtopic)
    {
        $topictitleformat = array();
            $topictitleformat[1] = "(A favor)";
            $topictitleformat[2] = "(Contra)";
        
        $getfirstsectionidnumber = explode(']', $getgroupnewtopic->idnumber);
        $getmagicalnumber = explode('[', $getfirstsectionidnumber[0]);

        if($getmagicalnumber[1] == true){
            $newtopicname = "Grupo " . $countgrouprecords . ' ' .  $topictitleformat[$getmagicalnumber[1]];
            
            $discussion = new stdClass();
            $discussion->course        = $courseid;
            $discussion->forum         = $forum->id;
            $discussion->name          = $newtopicname;
            $discussion->message       = $forum->intro;
            $discussion->messageformat = 1;
            $discussion->messagetrust  = trusttext_trusted(context_course::instance($courseid));
            $discussion->groupid       = $getgroupnewtopic->id;
            $discussion->mailnow       = false;
            $message = '';
            $discussion->id = forum_add_discussion($discussion, null, $message);
            $countgrouprecords++;
        }
    }
}