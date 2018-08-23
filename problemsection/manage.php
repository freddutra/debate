<?php
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
 * File : manage.php
 * To manage the problem sections in this course.
 */

require_once("../../config.php");
require_once("lib.php");

// Arguments.
$courseid = required_param('id', PARAM_INT);
$deletedproblemsectionid = optional_param('delete', 0, PARAM_INT);
$deletedproblemsectionaction = optional_param('mode', null, PARAM_RAW);

// Access control.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code.
$manageurl = new moodle_url('/local/problemsection/manage.php', array('id' => $courseid));
if ($deletedproblemsectionid) {
    $pageurl = new moodle_url('/local/problemsection/manage.php',
            array('id' => $courseid, 'delete' => $deletedproblemsectionid));
} else {
    $pageurl = $manageurl;
}
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);
$title = get_string('manage', 'local_problemsection');
$PAGE->set_title($title);
$PAGE->set_heading($title);

// Deleting the problem section.
if ($deletedproblemsectionid && confirm_sesskey()) {
    $deletionparams = array('id' => $deletedproblemsectionid, 'courseid' => $courseid);
    
    if($deletedproblemsectionaction == "task"){
        $deletedproblemsection = $DB->get_record('local_problemsection', $deletionparams);
        if ($deletedproblemsection) {
            $deletedsection = $DB->get_record('course_sections', array('id' => $deletedproblemsection->sectionid));
            // Get section_info object with all availability options.
            $sectionnum = $deletedsection->section;
            $sectioninfo = get_fast_modinfo($course)->get_section_info($sectionnum);

            if (course_can_delete_section($course, $sectioninfo)) {
                $confirm = optional_param('confirm', false, PARAM_BOOL) && confirm_sesskey();
                if ($confirm) {
                    local_problemsection_delete($deletedproblemsection, $course, $sectioninfo);
                    redirect($manageurl);
                } else {
                    $strdelete = get_string('deleteproblemsection', 'local_problemsection');
                    $PAGE->navbar->add($strdelete);
                    $PAGE->set_title($strdelete);
                    $PAGE->set_heading($course->fullname);
                    echo $OUTPUT->header();
                    echo $OUTPUT->box_start('noticebox');
                    $optionsyes = array('id' => $courseid, 'confirm' => 1,
                        'delete' => $deletedproblemsectionid, 'sesskey' => sesskey());
                    $deleteurl = new moodle_url('/local/problemsection/manage.php', $optionsyes);
                    $formcontinue = new single_button($deleteurl, get_string('deleteproblemsection', 'local_problemsection'));
                    $formcancel = new single_button($manageurl, get_string('cancel'), 'get');
                    echo $OUTPUT->confirm(get_string('warningdelete', 'local_problemsection',
                        $deletedproblemsection->name), $formcontinue, $formcancel);
                    echo $OUTPUT->box_end();
                    echo $OUTPUT->footer();
                    exit;
                }
            } else {
                notice(get_string('nopermissions', 'error', get_string('deletesection')), $manageurl);
            }
        }
    }
    
    if($deletedproblemsectionaction == "action"){
        $deletedproblemsection = $DB->get_record('local_problemsection_groups', $deletionparams);
        if ($deletedproblemsection) {
            $confirm = optional_param('confirm', false, PARAM_BOOL) && confirm_sesskey();
            if ($confirm) {
                local_problemsection_deleteaction($deletedproblemsectionid);
                redirect($manageurl);
            } else {
                $strdelete = get_string('deleteproblemsection', 'local_problemsection');
                $PAGE->navbar->add($strdelete);
                $PAGE->set_title($strdelete);
                $PAGE->set_heading($course->fullname);
                echo $OUTPUT->header();
                echo $OUTPUT->box_start('noticebox');
                $optionsyes = array('id' => $courseid, 'confirm' => 1,
                    'delete' => $deletedproblemsectionid, 'sesskey' => sesskey());
                $deleteurl = new moodle_url('/local/problemsection/manage.php', $optionsyes);
                $formcontinue = new single_button($deleteurl, get_string('deleteproblemsection', 'local_problemsection'));
                $formcancel = new single_button($manageurl, get_string('cancel'), 'get');
                echo $OUTPUT->confirm(get_string('warningdelete', 'local_problemsection',
                    $deletedproblemsection->name), $formcontinue, $formcancel);
                echo $OUTPUT->box_end();
                echo $OUTPUT->footer();
                exit;
            }
        } else {
            notice(get_string('nopermissions', 'error', get_string('deletesection')), $manageurl);
        }
    }
}

$problemsections = $DB->get_records('local_problemsection', array('courseid' => $courseid));
$addurl = "problemsection.php?id=$courseid";
$debateurl = "createdebate.php?id=$courseid";
$commongroupsurl = "groups.php?id=$courseid&psid=";
$commonsubmissionsurl = "$CFG->wwwroot/mod/assign/view.php?action=grading&id=";
$commondeleteurl = "manage.php?id=$courseid&mode=task&sesskey=".s(sesskey())."&delete=";
$commondeleteactionurl = "manage.php?id=$courseid&mode=action&sesskey=".s(sesskey())."&delete=";

if($DB->record_exists('local_problemsection_status', array('courseid'=>$courseid)) != 1){header('Location: problemsection.php?id='.$courseid);}

$statusdata = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid));

echo $OUTPUT->header();
//echo "<a href='$addurl'><button>Adicionar debate crítico</button></a>";

echo "<h4 class='debate-menage-header-style'>Atividades</h4>";
if ($problemsections) {
    echo '<table class="debate-menage-table">';
    echo '<tr>';
    echo '<th>'.get_string('name').'</th>';
    echo '<th>'.get_string('groups').'</th>';
    echo '<th>'.get_string('submissions', 'local_problemsection').'</th>';
    echo '<th>'.get_string('allowsubmissionsfromdate', 'assign').'</th>';
    echo '<th>'.get_string('duedate', 'assign').'</th>';
    echo '<th>Ação</th>';
    echo '</tr>';
    foreach ($problemsections as $problemsection) {
        $nbgroups = $DB->count_records('groupings_groups',
                array('groupingid' => $problemsection->groupingid));
        $groupsurl = $commongroupsurl.$problemsection->id;
        $assigncm = local_problemsection_get_assigncm($problemsection);
        echo '<tr>';
        echo "<td>$problemsection->name</td>";
        echo "<td style='text-align:center'><a href='$groupsurl'>$nbgroups</a></td>";
        if ($assigncm) {
            $submissionsurl = $commonsubmissionsurl.$assigncm->id;
            $assign = $DB->get_record('assign', array('id' => $assigncm->instance));
            $nbsubmissions = $DB->count_records('assign_submission', array('assignment' => $assign->id));
            echo "<td style='text-align:center'><a href='$submissionsurl'>$nbsubmissions</a></td>";
            echo '<td>'.date('d/m/Y H:i:s', $assign->allowsubmissionsfromdate).'</td>';
            echo '<td>'.date('d/m/Y H:i:s', $assign->duedate).'</td>';
        } else {
            echo '<td></td><td></td><td></td>';
        }
        echo "<td><a href='".$commondeleteurl.$problemsection->id."'><button>"
                .get_string('deleteproblemsection', 'local_problemsection')."</button></a></td>";
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>'.get_string('noproblemyet', 'local_problemsection').'</p>';
}

// ------------------------------------------------------------------
$refuteurl = "blind/createrefute.php?id=$courseid";
$lastdebateurl = "createlastdebate.php?id=$courseid";
$updatestudentsurl = "updatestudents.php?id=$courseid";

echo "<h4 class='debate-menage-header-style'>Ações do debate</h4>";

echo '<table class="debate-menage-table">';
echo '<tr>';
echo '<th>Ação</th>';
echo '<th>Descrição</th>';
echo '</tr>';

echo "<tr>";
echo "<td><a href='$debateurl'><button>Gerar estratégia inicial</button></a></td>";
echo "<td>Cria a estratégia inicial. Gera os seguintes itens:     
<br>* <b>Forum</b> de estratégia;
<br>* <b>Tópicos</b> de estratégia (invisíveis entre os grupos);
<br> Observação: A configuração do tamanho da turma é feito na tela inicial do plugin, porém, caso deseje alterar o valor, clique no botão abaixo:<br>
    <a href='$updatestudentsurl'><button>Alterar tamanho de grupo</button></a> Atualmete, o corte esta configurado em $statusdata->studentspergroup alunos.
    </td>";
echo "</tr>";

echo "<tr>";
echo "<td><a href='$refuteurl'><button>Gerar confrontação</button></a><br></td>";
echo "<td>Cria a confrontação. Gera os seguintes itens:     
<br>* <b>Forum</b> de confrontação;
<br>* <b>Grupos</b> de confrontação (Grupo 1 e 2, confrontação a favor/conta);
<br>* <b>Tópicos</b> de confrontação (invisíveis entre os grupos);
<br>* Torna o grupo de <i>debate</i> aberto para visualização dos materiais publicados.
    </td>";
echo "</tr>";

echo "<tr>";
echo "<td><a href='$lastdebateurl'><button>Gerar conclusão</button></a></td>";
echo "<td>Cria o debate final. Gera os seguintes itens:     
<br>* <b>Forum</b> para o debate final;
<br> Observações importantes:
<br>* Este tópico <b>não</b> tem grupo, sendo um debate aberto para toda a turma;
<br>* Os demais tópicos (defesa e confrontação) ficarão abertos para visualização dos alunos, porém com a habilidade de comentar desabilitada;
    </td>";
echo "</tr>";

echo '</table>';

echo $OUTPUT->footer();