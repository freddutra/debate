<?php
global $CFG, $PAGE, $USER, $SITE, $COURSE, $DB;

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/formslib.php');
require_once("$CFG->dirroot/course/lib.php");
require_once($CFG->dirroot.'/group/lib.php');

// Access control.
$courseid = required_param('id', PARAM_INT);
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
    // Make forum visible (open/no group)
    $DB->update_record("course", array(
        'id'=>$courseid,
        'groupmode'=>0,
        'groupmodeforce'=>0
    ));

    $getmodule = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid));

    // Create last forum (debate)
    $forum = new stdClass();
    $forum->course = $courseid;
    $forum->type = "single";    
    $forum->name = "Conclusão";
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

    $sectionid = course_add_cm_to_section($courseid, $mod->id, $getmodule->sessionid);
    
    $DB->set_field("course_modules", "section", $sectionid, array("id" => $mod->id));
    //rebuild_course_cache($courseid);

    // Criar tópico para fórum (evitar erro)
    $discussion = new stdClass();
    $discussion->course        = $courseid;
    $discussion->forum         = $forum->id;
    $discussion->name          = "Conclusão";
    $discussion->message       = $forum->intro;
    $discussion->messageformat = 1;
    $discussion->messagetrust  = 0;
    $discussion->groupid       = -1;
    $discussion->mailnow       = false;
    $message = '';
    $t = forum_add_discussion($discussion, null, $message);

    // Remove capability to reply from other posts
    //$contextadad = context_module::instance($courseid);
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
                // remover capacidade de resposta em fórum
                $registerexist = $DB->count_records('role_capabilities', 
                                    array(
                                        'contextid'=>$context->id, 
                                        'roleid'=>5, 
                                        'capability'=> 'mod/forum:replypost'));
                                        
                if($registerexist == 1) {
                    $datatoupdate = $DB->get_record('role_capabilities', 
                                    array(
                                        'contextid'=>$context->id, 
                                        'roleid'=>5, 
                                        'capability'=> 'mod/forum:replypost'));
                    $DB->update_record('role_capabilities', array('id'=>$datatoupdate->id, 'permission'=>"-1"));
                }
                else {
                    $DB->insert_record('role_capabilities', array(
                        'contextid'=>$context->id, 
                        'roleid'=>5, 
                        'capability'=> 'mod/forum:replypost',
                        'timemodified'=>time(),
                        'permission'=>"-1"));
                }
            }
        }
    }

    // Header
    header("Location: manage.php?id=$courseid&psid=");
}
catch(\Exception $e) {
    echo("Fail" . $e->getMessage());
}