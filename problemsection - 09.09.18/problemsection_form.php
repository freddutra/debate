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
 * Adds to the course a section where the teacher can submit a problem to groups of students
 * and give them various collaboration tools to work together on a solution.
 *
 * @package   local_problemsection
 * @copyright 2016 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : problemsection_form.php
 * Problem section edition form
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}
require_once($CFG->libdir.'/formslib.php');

class problemsection_form extends moodleform {
    public function definition() {
        global $OUTPUT, $DB;
        $error = optional_param('error', 0, PARAM_INT);

        $mform =& $this->_form;

        if($error == 1){
            $mform->addElement('static', 'warning', "Erro!", "O tamanho do grupo <b> NÃO </b> pode ser negativo.");
        }

        if($DB->record_exists('local_problemsection_status', array('courseid'=>$this->_customdata['courseid'])) != 1){
            $mform->addElement('static', 'warning', "Aviso de configuração", "O plugin 'Debate Crítico' ainda não foi configurado  no curso. <br>Para configurar, preencha os campos abaixo:");
        }

        
        $mform->addElement('header', 'generalhdr', get_string('general'));
        $mform->addElement('text', 'title', "Assunto do debate (título)");
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->setDefault('title', "Tema do debate");

        /*
        $mform->addElement('editor', 'directions', get_string('directions', 'local_problemsection'));
        $mform->addHelpButton('directions', 'directions', 'local_problemsection');
        $mform->setType('directions', PARAM_RAW);
        $mform->addRule('directions', get_string('required'), 'required', null, 'client');
        $mform->setDefault('directions', array('text'=>"Orientações gerais para o envio do trabalho final.", 'format'=>FORMAT_HTML));

        $mform->addElement('date_time_selector', 'datefrom', get_string('allowsubmissionsfromdate', 'assign'));
        $mform->addElement('date_time_selector', 'dateto', get_string('duedate', 'assign'));
        */

        $mform->addElement('header', 'generalhdr', "Carta de apresentação");
        
        $page = file_get_contents('carta.html');

        $mform->addElement('editor', 'pagecontent', "Instruções do exercício");
        $mform->setType('pagecontent', PARAM_RAW);
        $mform->addRule('pagecontent', get_string('required'), 'required', null, 'client');
        $mform->setDefault('pagecontent', array('text'=>$page, 'format'=>FORMAT_HTML));

        $mform->addElement('header', 'groupsizehdr', "Tamanho dos grupos de estratégia");
        $mform->addElement('static', 'description', "Grupo", "
            Determina o tamanho <b>máximo</b> de alunos em um grupo. 
            <br>O tamanho do grupo (valor abaixo), deverá retornar um numero par de grupos. 
            <br> Padrão do sistema: 4 alunos por grupo. 
            <br> Instrução para preenchimento: apenas números inteiros e acima de zero são permitidos.
        ");

        $mform->addElement('text', 'groupsize', "Tamanho dos grupos");
        $mform->setType('groupsize', PARAM_INT);
        $mform->setDefault('groupsize', 4);
        $mform->addRule('groupsize', null, 'required', null, 'client', true);
        $mform->addRule('groupsize', null, 'numeric', null, 'client', true);
        $mform->addRule('groupsize', null, 'nonzero', null, 'client', true);
        $mform->addRule('groupsize', null, 'nopunctuation', null, 'client', true);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons();
    }
}
