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

require_once("../../config.php");
require_once('lib.php');
require_once('blind/rundebate_lib.php');
require_once($CFG->libdir.'/formslib.php');

// Access control.
$courseid = required_param('id', PARAM_INT);
$debate = optional_param('debate', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('local/problemsection:addinstance', $context);

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid));
$PAGE->set_pagelayout('admin');

require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:managegroups', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code.
$pageurl = new moodle_url('/local/problemsection/createdebate.php', array('id' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);

$pagetitle = "Criar quiz de debate";
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
try{
    $getmodule = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid, 'problemid'=>$debate));
    $countanswers = $DB->get_records('choice_answers', array('choiceid'=>$getmodule->quizid));

    if(count($countanswers) <= 0){ 
        header("Location: manage.php?id=$courseid&action=13&debate=$debate&psid="); 
    }
    else{
        $returnchoicestudents = $DB->get_records('choice_answers', array('choiceid'=>$getmodule->quizid));
    
        //print_r($returnchoicestudents);
   
        //echo $courseid;
        // Converter o quiz em grupo
        //$courseidhack = $courseid+1; // Hack. Tentar resolver. Curso + 1(?)
        $avaliablestudent = array();
        $unavaliablestudent = 0;
        $context = context_course::instance($courseid);
        //$coursecontext = context_module::instance($courseid);
        //$studentsoriginal = $DB->get_records("user_enrolments", array('enrolid'=>$courseidhack)); 
    
        //echo "<pre>";
    
        //echo "<h1>context</h1>";
        //print_r($context);
    
        //echo "<h1>coursecontext</h1>";
        //print_r($coursecontext);
    
        $students = array();
        foreach($DB->get_records("enrol", array('courseid'=>$courseid)) as $enrol){
            //print_r($enrol);
            //echo "------------";
    
            $enrolments = $DB->get_records("user_enrolments", array('enrolid'=>$enrol->id));
            //array_push($students, $enrolments);
            //print_r($enrolments);
    
            foreach($enrolments as $student){
                if (!has_capability('mod/folder:managefiles', $context, $student->userid, false)) {
                    //echo $student->userid . "<br>";
                    $avaliablestudent[] = array('userid'=>$student->userid);
                }
                else{ $unavaliablestudent = $unavaliablestudent+1; }
            }
        }
        
        //echo "<h1>students</h1>";
        //print_r($avaliablestudent);
        
        if(count($avaliablestudent) <= 0){ header("Location: manage.php?id=$courseid&action=14&psid="); }
        if(count($countanswers) != count($avaliablestudent)){header("Location: manage.php?id=$courseid&action=12&psid=");}    
        
        $newgroupscount = count($avaliablestudent)/2;
    
        echo "<pre>";
        if(is_int($newgroupscount) == true){
            $currentstudents = 0;
            //print_r($returnchoicestudents);
            foreach($returnchoicestudents as $returnchoicestudent){
                //print_r($returnchoicestudent);
                $getgroups = $DB->get_records("groups", array('courseid'=>$courseid));
                $user = $DB->get_record('user', array('id'=>$returnchoicestudent->userid, 'deleted'=>0, 'mnethostid'=>$CFG->mnet_localhost_id), '*', MUST_EXIST);
     
                $data = new stdClass();
                $data->courseid = $courseid;
                $locateandcreatefirst = $DB->record_exists("groups", array('courseid'=>$courseid, 'name'=>"[DI]DebateCritico1"));
                $locateandcreatesecond = $DB->record_exists("groups", array('courseid'=>$courseid, 'name'=>"[DI]DebateCritico2"));
                
                $choiceoptions = array_values($DB->get_records('choice_options', array('choiceid'=>$getmodule->quizid)));
    
                if(
                    ($returnchoicestudent->optionid == $choiceoptions[0]->id) &&
                    ($currentstudents < $newgroupscount)
                ){
                    if($locateandcreatefirst == 1){
                        $groupid = $DB->get_record("groups", array('courseid'=>$courseid, 'name'=>"[DI]DebateCritico1"));
                        $group = groups_get_group($groupid->id, 'id, courseid', MUST_EXIST);
                        groups_add_member($group, $user);
                    }
                    else{
                        $data->name = "[DI]DebateCritico1"; 
                        $creanewgroup = groups_create_group($data);
                        $group = groups_get_group($creanewgroup, 'id, courseid', MUST_EXIST);
                        groups_add_member($group, $user);
                    }
                }
                else{
                    if($locateandcreatesecond == 1){
                        $groupid = $DB->get_record("groups", array('courseid'=>$courseid, 'name'=>"[DI]DebateCritico2"));
                        $group = groups_get_group($groupid->id, 'id, courseid', MUST_EXIST);
                        groups_add_member($group, $user);
                    }
                    else{
                        $data->name = "[DI]DebateCritico2"; 
                        $creanewgroup = groups_create_group($data);
                        $group = groups_get_group($creanewgroup, 'id, courseid', MUST_EXIST);
                        groups_add_member($group, $user);
                    }
                }
                $currentstudents++;
            }
            // Fecha o quiz
            $DB->update_record('choice', array('id'=>$getmodule->quizid, 'timeopen'=>time()-1, 'timeclose'=>time()));
            
            // Criação do fórum de debate
            $forum = create_forum($courseid, "Forum de debate", $debate); //OK
            //print_r($forum);
            // Subgrupo quiz
            $foruns = create_debate_groups($courseid, $debate); // OK (so-so)

            print_r($foruns);

            // Criação dos tópicos
            create_debate_topics($courseid, $forum, $foruns); // OK
    
            $DB->update_record('local_problemsection_status', array('id'=>$getmodule->id, 'problemid'=>$debate, 'forumdiscussionid'=>$forum->id));
    
            // Apaga [DC]
            $DB->delete_records("groups", array('courseid'=>$courseid, 'name'=>"[DI]DebateCritico1"));
            $DB->delete_records("groups", array('courseid'=>$courseid, 'name'=>"[DI]DebateCritico2"));
    
            header("Location: manage.php?id=$courseid&action=1&debate=$debate&psid=");
        }
        else{
            header("Location: manage.php?id=$courseid&action=10&debate=$debate&psid=");
        }
    }
}
catch(\Exception $e) {
    echo("Fail" . $e->getMessage());
}