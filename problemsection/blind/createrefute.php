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
 * Aplicação moficada :
 * xx
 * Criação de debate crítico (2018)
 * Parte integrante do Trabalho de Conclusão de Curso (xx)
 * 
 * Adds to the course a section where the teacher can submit a problem to groups of students
 * and give them various collaboration tools to work together on a solution.
 *
 * @package   local_problemsection
 * @copyright 2016 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : groups.php
 * Manage groups for this problem section.
 */

global $CFG, $PAGE, $USER, $SITE, $COURSE;

//require_once("../../config.php");
require_once dirname(dirname(dirname(__FILE__))).'/../config.php';
require_once('../lib.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/group/lib.php');
require_once('rundebate_lib.php');

// Access control.
$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('local/problemsection:addinstance', $context);

//$pageurl = new moodle_url('/local/problemsection/debateadm.php', array('id' => $courseid));
$course = $DB->get_record('course', array('id' => $courseid));

require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:managegroups', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code
$pageurl = new moodle_url('/local/problemsection/blind/createrefute.php', array('id' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);

$pagetitle = "Converter quiz em grupo";
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
try{
    $getmodulestatusid = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid));
    //print_r($getmodulestatusid);

    // Open forum (debate)
    $getforumdata = $DB->get_record('course_modules', array('course'=>$courseid, 'module'=>9, 'instance'=>$getmodulestatusid->forumdiscussionid));
    $DB->update_record('course_modules', array('id'=>$getforumdata->id, 'groupmode'=>2));

    // Create new forum
    $forum = create_forum($courseid, $getmodulestatusid->sessionid, "Forum de confrontação");

    // Load every group
    $returnallgroups = $DB->get_records('groups', array('courseid'=>$courseid));

    $debategroups = array();

    foreach($returnallgroups as $returnallgroup){
        if($returnallgroup->idnumber != ""){
            $getfirstsectionidnumber = explode(']', $returnallgroup->idnumber);
            $getmagicalnumber = explode('[', $getfirstsectionidnumber[0]);

            if(($getmagicalnumber[1] == 1) || ($getmagicalnumber[1] == 2)){
                $debategroups[] = $returnallgroup;
            }
        }
    }

    $newpairs = array_chunk($debategroups,2);
    $createdgroups = array();

    foreach($newpairs as $newpair){
        // Names
        $firstgroupname = $newpair[0]->name;
        $secondgroupname = $newpair[1]->name;

        $newgroupname = $firstgroupname . " e " . $secondgroupname;

        //create group
        $data = new stdClass();
        $data->courseid = $courseid;
        $data->name = $newgroupname;
        $newgroup = groups_create_group($data);
        
        // Get users from groups
        for($i = 0; $i <= 1; $i++){
            $getusersingroups = $DB->get_records('groups_members', array("groupid"=>$newpair[$i]->id));
            //print_r($getusersingroups);

            foreach($getusersingroups as $getuseringroup){
                //echo "rodou +1";
                $group = groups_get_group($newgroup, 'id, courseid', MUST_EXIST);
                $user = $DB->get_record('user', array('id'=>$getuseringroup->userid));
                @groups_add_member($group, $user);
                //print_r($group);
            }
        }

        @$createdgroups[] = array("name" => $newgroupname, "id"=> $group->id);
    }
    //echo "<pre>";
    //print_r($createdgroups);

    // Create post for each new group
    //echo "<pre>";
    foreach($createdgroups as $createdgroup){
        //print_r($createdgroup);
        //echo "ID > " . $createdgroup["id"];
        $topictitleformat = array();
            $topictitleformat[1] = "(confrontação a favor)";
            $topictitleformat[2] = "(confrontação contra)";

        $discussion = new stdClass();
        $discussion->course        = $courseid;
        $discussion->forum         = $forum->id;
        $discussion->message       = $forum->intro;
        $discussion->messageformat = 1;
        $discussion->messagetrust  = trusttext_trusted(context_course::instance($courseid));
        $discussion->groupid       = $createdgroup["id"];
        $discussion->mailnow       = false;
        $message = '';

        if($createdgroup["id"] != ""){
            for($i = 1; $i <= 2; $i++){
                //echo $topictitleformat[$i];
                $discussion->name          = $createdgroup["name"] . ' ' . $topictitleformat[$i];
                $discussion->id = forum_add_discussion($discussion, null, $message);
                //echo $discussion->name;
                //echo "<br>|||||||||||||||||||||||||||||||||||||||||||<br>";
            }
        }
        //echo "<br>************************************************<br>";
    }

    header("Location: ../manage.php?id=$courseid&psid=");
}
catch(\Exception $e) {
    echo("Fail" . $e->getMessage());
}