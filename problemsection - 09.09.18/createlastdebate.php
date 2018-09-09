<?php
// V. 99
global $CFG, $PAGE, $USER, $SITE, $COURSE, $DB;

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/formslib.php');
require_once("$CFG->dirroot/course/lib.php");
require_once($CFG->dirroot.'/group/lib.php');

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

require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:managegroups', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code.
$pageurl = new moodle_url('/local/problemsection/createlastdebate.php', array('id' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);

$pagetitle = "Criar conclusão (debate final)";
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
try{
    echo "<pre>";
    $getmodule = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid, 'problemid'=>$debate, 'problemid'=>$debate));
    $returnsessionid = $DB->get_record('course_sections', array('id'=>$getmodule->sessionid));

    if(($getmodule->forumdiscussionid <= 0) || ($getmodule->forumrefuteid <= 0)){
        echo "erro";
        //header("Location: manage.php?id=$courseid&action=3&debate=$debate&psid=");
    }
    else{
        
        //echo "hey";
        //print_r($DB->get_records('course_modules', array('instance'=>343)));
        // Open foruns
        $forumid = $DB->get_record('course_modules', array('instance'=>$getmodule->forumdiscussionid));
        $DB->update_record("course_modules", array(
            'id'=>$forumid->id,
            'instance'=>$getmodule->forumdiscussionid,
            'groupmode'=>0
        ));

        $refuteid = $DB->get_record('course_modules', array('instance'=>$getmodule->forumrefuteid));
        $DB->update_record("course_modules", array(
            'id'=>$refuteid->id,
            'instance'=>$getmodule->forumrefuteid,
            'groupmode'=>0
        ));

        // Remove capability to reply from other posts
        //$contextadad = context_module::instance($courseid);
        /*
        $returnforumlist = $DB->get_records('forum', array('course'=>$courseid));
        foreach($returnforumlist as $forum){
            //echo "Forum ID: " . $forum->id;
            $getcoursemoduleinfo = $DB->get_records('course_modules', array('course' => $courseid, 'instance'=>$forum->id, 'module'=>9));
            foreach($getcoursemoduleinfo as $gcontext){
                //$getcontext = $DB->get_records('context', array('instanceid'=>$gcontext->id, 'contextlevel'=>$contextadad->contextlevel));
                $getcontext = $DB->get_records('context', array('instanceid'=>$gcontext->id));
                //print_r($getcontext);
                //echo $gcontext->id . "<br>";
                foreach($getcontext as $context){
                    // mod/forum:replypost =  -1
                    try{$DB->insert_record('role_capabilities', array('contextid'=>$context->id, 'roleid'=>5, 'capability'=> 'mod/forum:replypost', 'timemodified'=>time(), 'permission'=>"-1"));}catch(\Exception $e){}
                    // forum:deleteownpost =  -1
                    try{$DB->insert_record('role_capabilities', array('contextid'=>$context->id, 'roleid'=>5, 'capability'=> 'mod/forum:deleteownpost', 'timemodified'=>time(), 'permission'=>"-1"));}catch(\Exception $e){}
                    // forum:exportownpost =  -1
                    try{$DB->insert_record('role_capabilities', array('contextid'=>$context->id, 'roleid'=>5, 'capability'=> 'mod/forum:exportownpost', 'timemodified'=>time(), 'permission'=>"-1"));}catch(\Exception $e){}
                }
            }
        }*/

        // confrontação - Read Only
        try{
            $getforumdata = $DB->get_record('course_modules', array('course'=>$courseid, 'module'=>9, 'instance'=>$getmodule->forumrefuteid));
            $forumdata = $DB->get_record('context', array('instanceid'=>$getforumdata->id));
            role_change_permission(5, $forumdata, "mod/forum:replypost", -1000);
            role_change_permission(5, $forumdata, "mod/forum:deleteownpost", -1000);
            role_change_permission(5, $forumdata, "mod/forum:exportownpost", -1000);
        }catch(\Exception $e){}
            
        // Create last forum (debate)
        $forum = new stdClass();
        $forum->course = $courseid;
        $forum->type = "blog";    
        $forum->name = "Conclusão" . " (" . $getmodule->debatename . ")";
        $forum->intro = "";
        $forum->timemodified = time();
        $forum->id = $DB->insert_record("forum", $forum);
        //print_r($forum);

        $mod = new stdClass();
        $mod->course = $courseid;
        $mod->module = 9; // Module id (9 = forum)
        $mod->instance = $forum->id;
        $mod->section = 0;
        $mod->added = time();
        $mod->id = add_course_module($mod);
        //print_r($mod);

        $sectionid = course_add_cm_to_section($courseid, $mod->id, $returnsessionid->section);
        //print_r($sectionid);
        
        $DB->set_field("course_modules", "section", $sectionid, array("id" => $mod->id));
        //rebuild_course_cache($courseid);

        // -------------------------
        $avaliablestudent = array();
        foreach($DB->get_records("enrol", array('courseid'=>$courseid)) as $enrol){
            $enrolments = $DB->get_records("user_enrolments", array('enrolid'=>$enrol->id));
            foreach($enrolments as $student){
                if (!has_capability('mod/folder:managefiles', $context, $student->userid, false)) {
                    $avaliablestudent[] = array('userid'=>$student->userid);
                }
            }
        }

        // Create new group
        $data = new stdClass();
        $data->courseid = $courseid;
        $data->name = "Discussão final"; 
        $creanewgroup = groups_create_group($data);
        $group = groups_get_group($creanewgroup, 'id, courseid', MUST_EXIST);

        //print_r($group);

        foreach($avaliablestudent as $student){
            $user = $DB->get_record('user', array('id'=>$student["userid"], 'deleted'=>0, 'mnethostid'=>$CFG->mnet_localhost_id), '*', MUST_EXIST);
            //print_r($user);
            groups_add_member($group, $user);
        }
        // -----------------------

        // Criar tópico para fórum (evitar erro)
        $discussion = new stdClass();
        $discussion->course        = $courseid;
        $discussion->forum         = $forum->id;
        $discussion->name          = "Conclusão";
        $discussion->message       = $forum->intro;
        $discussion->messageformat = 1;
        $discussion->messagetrust  = 0;
        $discussion->groupid       = $group->id;
        $discussion->mailnow       = false;
        $message = '';
        $t = forum_add_discussion($discussion, null, $message);

        // Create grouping
        $grouping = new stdClass();
        $grouping->courseid = $courseid;
        $grouping->name = "Debate Crítico - ".$getmodule->debatename." (conclusão)";
        $grouping->description = "Grupo final de debate";
        $grouping->timecreated = time();
        $grouping->timemodified = time();
        $grouping->id = $DB->insert_record('groupings', $grouping, true);
        
        // Create assign (try)
        $assign = new stdClass();
        $assign->course = $courseid;
        $assign->name = "Trabalho final do debate";
        $assign->intro = "";
        $assign->introformat = 1;
        $assign->teamsubmission = 1;
        $assign->teamsubmissiongroupingid = -1;
        $assign->duedate = time() + (10 * 24 * 60 *60);
        $assign->allowsubmissionsfromdate = time();
        $assign->timemodified = time();
        $newassignment = $DB->insert_record('assign', $assign, true); 

        $mod = new stdClass();
        $mod->course = $courseid;
        $mod->module = 1;
        $mod->instance = $newassignment;
        $mod->section = 0;
        $mod->added = time();
        $mod->id = add_course_module($mod);
        $sectionid = course_add_cm_to_section($courseid, $mod->id, $returnsessionid->section);

        $DB->set_field("course_modules", "section", $sectionid, array("id" => $mod->id));

        rebuild_course_cache($courseid);

        // Header
        header("Location: manage.php?id=$courseid&action=3&debate=$debate&psid=");
    }
}
catch(\Exception $e) {
    echo("Fail" . $e->getMessage());
}