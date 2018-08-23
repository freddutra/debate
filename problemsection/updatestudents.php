<?php
global $CFG, $PAGE, $USER, $SITE, $COURSE;

require_once('../../config.php');
require_once('lib.php');
require_once('blind/updatestudents_form.php');
require_once($CFG->libdir.'/formslib.php');

// Access control.
$courseid = required_param('id', PARAM_INT);
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

// Header code.
$pageurl = new moodle_url('/local/problemsection/updatestudents.php', array('id' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);

$pagetitle = "Atualizar tamanho dos grupos";
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$mform = new groupaction_form($pageurl);
$courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
if ($mform->is_cancelled()) {
    redirect($courseurl);
} else if ($submitteddata = $mform->get_data()) {
    try{
        $statusdata = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid));
        //print_r($statusdata);
        $DB->update_record('local_problemsection_status', array('id'=>$statusdata->id, 'studentspergroup'=>$submitteddata->groupsize));

        header("Location: manage.php?id=$courseid&psid=");
    }
    catch(\Exception $e) {
        echo("Fail" . $e->getMessage());
    }
} else {
    echo $OUTPUT->header();
    $mform->display();
    echo $OUTPUT->footer();
}