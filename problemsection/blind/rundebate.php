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
require_once('rundebate_lib.php');
require_once($CFG->libdir.'/formslib.php');

// Access control.
$courseid = required_param('id', PARAM_INT);
$sectionid = required_param('sectionid', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('local/problemsection:addinstance', $context);

$courseid = required_param('id', PARAM_INT);
$paramgroupid = optional_param('groupid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$changedgroupid = optional_param('changed', 0, PARAM_INT);

//$pageurl = new moodle_url('/local/problemsection/debateadm.php', array('id' => $courseid));
$course = $DB->get_record('course', array('id' => $courseid));
$PAGE->set_pagelayout('admin');

require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:managegroups', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code.
//$pageurl = new moodle_url('/local/problemsection/problemsection.php', array('id' => $courseid));
//$pageurl = new moodle_url('/local/problemsection/blind/rundebate.php', array('id' => $courseid, 'sectionid'=>$sectionid));
$pageurl = new moodle_url('/local/problemsection/blind/rundebate.php', array('id' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);

$pagetitle = "Gerar status do modulo";
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
try{
    ///echo "<pre>";
    $getmodulestatusid = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid));
    
    // First step: create forum
    $forum = create_forum($courseid, $sectionid, "Forum de estratégia"); //OK
    $DB->update_record('local_problemsection_status', array('id'=>$getmodulestatusid->id, 'forumcreated'=>1));

    // Second step: create subgroups from quiz
    create_debate_groups($courseid); // OK (so-so)
    $DB->update_record('local_problemsection_status', array('id'=>$getmodulestatusid->id, 'groupscreated'=>1));

    // Third step: create topics (with privacy)
    create_debate_topics($courseid, $forum); // OK
    $DB->update_record('local_problemsection_status', array('id'=>$getmodulestatusid->id, 'postscreated'=>1));

    //echo "</pre>";
    header("Location: ../manage.php?id=$courseid&psid=");
}
catch(\Exception $e) {
    echo("Fail" . $e->getMessage());
}