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
// ----------------------------------------------
$debate = optional_param('debate', 0, PARAM_INT);
$returnaction = optional_param('action', 0, PARAM_INT);
// ----------------------------------------------
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
    $deletedproblemsection = $DB->get_record('local_problemsection', $deletionparams);
    if ($deletedproblemsection) {
        $deletedsection = $DB->get_record('course_sections', array('id' => $deletedproblemsection->sectionid));
        // Get section_info object with all availability options.
        $sectionnum = $deletedsection->section;
        $sectioninfo = get_fast_modinfo($course)->get_section_info($sectionnum);

        if(!$deletedsection){
            $DB->delete_records('local_problemsection', array('id'=>$deletedproblemsection->id));
            redirect($manageurl);
        }

        if (course_can_delete_section($course, $sectioninfo)) {
            $confirm = optional_param('confirm', false, PARAM_BOOL) && confirm_sesskey();
            if ($confirm) {
                local_problemsection_delete($deletedproblemsection, $course, $sectioninfo);
                if($DB->record_exists("local_problemsection_status", array("problemid"=>$deletedproblemsection->id)) == true){
                    $DB->delete_records("local_problemsection_status", array("problemid"=>$deletedproblemsection->id));
                }
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
$commongroupsurl = "groups.php?id=$courseid&psid=";
$commonsubmissionsurl = "$CFG->wwwroot/mod/assign/view.php?action=grading&id=";
$commondeleteurl = "manage.php?id=$courseid&sesskey=".s(sesskey())."&delete=";

if($DB->record_exists('local_problemsection_status', array('courseid'=>$courseid)) != 1){header('Location: problemsection.php?id='.$courseid);}

echo $OUTPUT->header();

// Load Jquery
echo "<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js'></script>";
// Load sweetalert
echo "<script src='https://unpkg.com/sweetalert/dist/sweetalert.min.js'></script>";

if($returnaction == 1){echo "<div class='action-return'>Estratégia gerada com sucesso!</div>";}
elseif($returnaction == 2){echo "<div class='action-return'>Confrontação gerada com sucesso!</div>";}
elseif($returnaction == 3){echo "<div class='action-return'>Conclusão gerada com sucesso!</div>";}
elseif($returnaction == 4){echo "<div class='action-return'>Debate crítico criado com sucesso!</div>";}
elseif($returnaction == 5){echo "<div class='action-return'>Tamanho dos grupos atualizado com sucesso!</div>";}
elseif($returnaction == 10){echo "<div class='action-return-error'>Falha ao criar a estratégia. Numero de alunos não retorna um numero <b>par</b> de grupos.</div>";}
elseif($returnaction == 11){echo "<div class='action-return-error'>Associe os alunos com a turma. <br> Falha ao criar a Estratégia inicial. Numero de alunos menor que o limite por grupo. Verifique o <i> enrol </i> de alunos do curso (associar alunos ao curso - enrol). <br> Lembre-se: Todo curso deverá ter um professor (não administrador). </div>";}
// -----------------------------------------------------------------------------------------------------------
elseif($returnaction == 12){echo "<div class='action-return-error'>O número de alunos não corresponde ao total de respostas do <i>quiz</i>. <br> Verifique se TODOS os alunos responderam o quiz e tente novamente.</div>";}
elseif($returnaction == 13){echo "<div class='action-return-error'>O quiz ainda não foi respondido pelos alunos.</div>";}
elseif($returnaction == 14){echo "<div class='action-return-error'>Não existem alunos associados a este curso.</div>";}
elseif($returnaction == 15){echo "<div class='action-return-error'>Não foi possível gerar a confrontação: Estratégia ainda não foi criada.<br>Verifique se a estratégia foi criada para o debate selecionado e tente novamente.</div>";}
elseif($returnaction == 16){echo "<div class='action-return-error'>Não foi possível gerar a conclusão. Verifique se os fóruns de estrategia e confrontação foram gerados corretamente no debate selecionado.</div>";}

$newbedate = "problemsection.php?id=$courseid";
$removedebate = "removedebate.php?id=$courseid";
$selectdebate = "manage.php?id=$courseid&debate=";

echo "<a href='$newbedate'><button style='width:160px'>Adiconar debate</button></a><br>";

echo "<h4 class='debate-menage-header-style'>Debates</h4>";
if ($problemsections) {
    echo '<table class="debate-menage-table">';
    echo '<tr>';
    echo '<th></th>';
    echo '<th>'.get_string('name').'</th>';
    echo '<th></th>';
    echo '</tr>';
    foreach ($problemsections as $problemsection) {
        echo '<tr>';
        if(($debate > 0) && ($debate == $problemsection->id)){
            echo "<td><a href=''><button disabled>Curso selecionado</button></a></td>";
        }
        else{
            echo "<td><a href='".$selectdebate.$problemsection->id."'><button>Selecionar debate</button></a></td>";
        }
        echo "<td>".$problemsection->name."</td>";
        echo "<td><a href='".$commondeleteurl.$problemsection->id."'><button>"
                .get_string('deleteproblemsection', 'local_problemsection')."</button></a></td>";
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>'.get_string('noproblemyet', 'local_problemsection').'</p>';
}

if(!isset($debate) || ($debate <= 0)){
    echo "<br><div class='action-return-error'><b>Nenhum</b> debate selecionado. <br> Para adicionar grupo de estratégia, confrotação e conclusão é necessário selelecionar um debate.</div>";
}
else{
    $statusdata = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid, 'problemid'=>$debate));

    // ------------------------------------------------------------------
    $refuteurl = "blind/createrefute.php?id=$courseid&debate=".$debate;
    $debateurl = "createdebate.php?id=$courseid&debate=".$debate;
    $lastdebateurl = "createlastdebate.php?id=$courseid&debate=".$debate;
    $updatestudentsurl = "updatestudents.php?id=$courseid&debate=".$debate;

    echo "<br>";
    echo "<h4 class='debate-menage-header-style'>Ações do debate</h4>";

    echo '<table class="debate-menage-table">';
    echo '<tr>';
    echo '<th>Ação</th>';
    echo '<th>Descrição</th>';
    echo '</tr>';

    echo "<tr>";
    echo "<td><a href='$debateurl' class='buttonaction'><button style='width:160px'>Gerar estratégia</button></a></td>";
    echo "<td style='padding-left:15px'>Cria a estratégia inicial. Gera os seguintes itens:     
    <br>* <b>Fórum</b> de estratégia;
    <br>* <b>Tópicos</b> de estratégia (invisíveis entre os grupos);
    <br> Observação: A configuração do tamanho da turma é feito na tela inicial do plugin. Para alterar o valor, clique no botão abaixo:<br>
        <a href='$updatestudentsurl'><button>Alterar tamanho de grupo</button></a> Atualmente, o corte esta configurado em $statusdata->studentspergroup alunos.
        </td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td><a href='$refuteurl' class='buttonaction'><button style='width:160px'>Gerar confrontação</button></a><br></td>";
    echo "<td style='padding-left:15px'>Cria a confrontação. Gera os seguintes itens:     
    <br>* <b>Fórum</b> de confrontação;
    <br>* <b>Grupos</b> de confrontação (Grupo 1 e 2, confrontação a favor/contra);
    <br>* <b>Tópicos</b> de confrontação (invisíveis entre os grupos);
    <br>* Torna o grupo de <i>estratégia</i> aberto para visualização dos materiais publicados.
        </td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td><a href='$lastdebateurl' class='buttonaction'><button style='width:160px'>Gerar conclusão</button></a></td>";
    echo "<td style='padding-left:15px'>Cria o debate final. Gera os seguintes itens:     
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
        swal({
        title: "Execução de tarefa",
        text: "Aguarde enquanto a operação está sendo executada.",
        icon: "success",
        button: false
        });
    });
    </script>
    ';
}

echo $OUTPUT->footer();