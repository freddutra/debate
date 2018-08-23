<?php

require_once("../../config.php");
require_once("lib.php");
require_once("problemsection_form.php");
require_once($CFG->libdir.'/formslib.php');

// Access control.
$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code.
$pageurl = new moodle_url('/local/problemsection/problemsection.php', array('id' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);
$title = "Adicionar debate crítico";
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add(get_string('manage', 'local_problemsection'), new moodle_url("/local/problemsection/manage.php?id=$courseid"));
$PAGE->navbar->add(get_string('problemsection:addinstance', 'local_problemsection'), new moodle_url($pageurl));
//$PAGE->navbar->add(get_string('enablvisibility', 'local_problemsection'), "");
//$PAGE->navbar->add(get_string('enablvisibility'), new moodle_url('/a/link/if/you/want/one.php'));

// Prepare datas for the form.
$potentialtools = local_problemsection_potentialtools($context);
$tools = array();
foreach ($potentialtools as $potentialtool) {
    $enabled = $DB->record_exists('modules', array('name' => $potentialtool, 'visible' => 1));
    if ($enabled) {
        if (has_capability("mod/$potentialtool:addinstance", $context)) {
            $tools[] = $potentialtool;
        }
    }
}
$coursegroupings = $DB->get_records('groupings', array('courseid' => $courseid));
$groupingoptions = array();
$groupingoptions[0] = ' - ';
foreach ($coursegroupings as $coursegrouping) {
    $groupingoptions[$coursegrouping->id] = $coursegrouping->name;
}

// Form instanciation.
$customdatas = array('courseid' => $courseid, 'tools' => $tools, 'copygrouping' => $groupingoptions);
$mform = new problemsection_form($pageurl, $customdatas);

// Three possible states.
if ($mform->is_cancelled()) {
    $courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
    redirect($courseurl);
} else if ($submitteddata = $mform->get_data()) {
    local_problemsection_create($submitteddata);
    //header("Location: groups.php?id=$courseid&psid=$problemsectionid");
    header("Location: manage.php?id=$courseid&psid=");
} else {
    echo $OUTPUT->header();
    $mform->display();
    echo $OUTPUT->footer();
}
