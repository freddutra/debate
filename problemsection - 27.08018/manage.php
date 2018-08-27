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
$returnaction = optional_param('action', 0, PARAM_INT);
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

echo "<script src='https://code.jquery.com/jquery-3.3.1.slim.min.js'></script>";

if($returnaction == 1){echo "<div class='action-return'>Estratégia gerada com sucesso!</div>";}
elseif($returnaction == 2){echo "<div class='action-return'>Confrontação gerada com sucesso!</div>";}
elseif($returnaction == 3){echo "<div class='action-return'>Conclusão gerada com sucesso!</div>";}
elseif($returnaction == 4){echo "<div class='action-return'>Debate crítico criado com sucesso!</div>";}
elseif($returnaction == 5){echo "<div class='action-return'>Tamanho dos grupos atualizado com sucesso!</div>";}
elseif($returnaction == 10){echo "<div class='action-return-error'>Falha ao criar a estratégia. Numero de alunos não retorna um numero <b>par</b> de grupos.</div>";}
elseif($returnaction == 11){echo "<div class='action-return-error'>Associe os alunos com a turma. <br> Falha ao criar a Estratégia inicial. Numero de alunos menor que o limite por grupo. Verifique o <i> enrol </i> de alunos do curso (associar alunos ao curso - enrol). <br> Lembre-se: Todo curso deverá ter um professor (não administrador). </div>";}

echo "<h4 class='debate-menage-header-style'>Atividades</h4>";
if ($problemsections) {
    echo '<table class="debate-menage-table">';
    echo '<tr>';
    echo '<th>'.get_string('name').'</th>';
    echo '</tr>';
    foreach ($problemsections as $problemsection) {
        echo '<tr>';
        echo "<td>$problemsection->name</td>";
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
echo "<td><a href='$debateurl' class='buttonaction'><button>Gerar estratégia inicial</button></a></td>";
echo "<td>Cria a estratégia inicial. Gera os seguintes itens:     
<br>* <b>Fórum</b> de estratégia;
<br>* <b>Tópicos</b> de estratégia (invisíveis entre os grupos);
<br> Observação: A configuração do tamanho da turma é feito na tela inicial do plugin. Para alterar o valor, clique no botão abaixo:<br>
    <a href='$updatestudentsurl'><button>Alterar tamanho de grupo</button></a> Atualmente, o corte esta configurado em $statusdata->studentspergroup alunos.
    </td>";
echo "</tr>";

echo "<tr>";
echo "<td><a href='$refuteurl' class='buttonaction'><button>Gerar confrontação</button></a><br></td>";
echo "<td>Cria a confrontação. Gera os seguintes itens:     
<br>* <b>Fórum</b> de confrontação;
<br>* <b>Grupos</b> de confrontação (Grupo 1 e 2, confrontação a favor/contra);
<br>* <b>Tópicos</b> de confrontação (invisíveis entre os grupos);
<br>* Torna o grupo de <i>estratégia</i> aberto para visualização dos materiais publicados.
    </td>";
echo "</tr>";

echo "<tr>";
echo "<td><a href='$lastdebateurl' class='buttonaction'><button>Gerar conclusão</button></a></td>";
echo "<td>Cria o debate final. Gera os seguintes itens:     
<br>* <b>Fórum</b> para o debate final;
<br> Observações importantes:
<br>* Este tópico <b>não</b> tem grupo, sendo um debate aberto para toda a turma;
<br>* Os demais tópicos (estratégia e confrontação) ficarão abertos para visualização dos alunos, porém impedidos de comentar nos tópicos;
    </td>";
echo "</tr>";

echo '</table>';

echo '
<script>
$( ".buttonaction" ).click(function() {
    alert( "Clique em OK para executar a ação. \nAo clicar, a página aparecerá como carregando. Este comportamento é normal enquanto a ação está sendo executada. \nNão atualize ou feche a página durante sua execução." );
  });
</script>
';

echo $OUTPUT->footer();