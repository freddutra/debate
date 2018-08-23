<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}
require_once($CFG->libdir.'/formslib.php');

class groupaction_form extends moodleform {
    public function definition() {
        global $OUTPUT, $DB;

        $mform =& $this->_form;
        
        $mform->addElement('static', 'description', "Grupo", "Determina o tamanho <b>máximo</b> dos alunos por grupo. <br>* Evite colocar números altos, pois, se o número de alunos for inferior ao valor de corte, o sistema retornará <i>erro</i>. <br> * Padrão do sistema: 4 alunos por grupo. <br> * <b>Instrução</b> para preenchimento: apenas números acima de zero são permitidos.");

        $mform->addElement('text', 'groupsize', "Tamanho dos grupos");
        $mform->setType('name', PARAM_INT);
        $mform->setDefault('groupsize', 4);
        
        $this->add_action_buttons();
    }
}