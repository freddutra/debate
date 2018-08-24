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
 * A scheduled task for Report Custom SQL.
 *
 * @package report_customsql
 * @copyright 2015 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//https://moodle.org/mod/forum/discuss.php?d=335643 << SUPER UTIL

//namespace local_problemsection\task;
//namespace local_import_timetable\task;
namespace local_problemsection\task;

//class local_problemsection extends \core\task\scheduled_task {

class run_reports extends \core\task\scheduled_task{
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    
    public function get_name() {
        // Shown in admin screens
        return get_string('pluginname', 'local_problemsection');
    }
    
    public function execute() {
        global $CFG, $DB;        
                
        $timestamp = time(); // now
        
        //$cronresults = $DB->get_records("local_problemsection_groups",array('runtime'=>$timestamp, "status"=>1));
        $cronresults = $DB->get_records("local_problemsection_groups",array('runtime'=>1534201561, "status"=>1));

        foreach($cronresults as $result){
            try {
                
                // Abrir grupos
                if($result->action == 1){
                    $DB->update_record("course", array(
                        'id'=>$result->courseid,
                        'groupmode'=>1,
                        'groupmodeforce'=>1
                    ));
                }
                
                // Fechar grupos
                if($result->action == 2){
                    $DB->update_record("course", array(
                        'id'=>$result->courseid,
                        'groupmode'=>2,
                        'groupmodeforce'=>1
                    ));
                }
                
                // Dividir grupos
                // 3
                if($result->action == 3){
                    $context = \context_course::instance($result->courseid);
                    $students = get_role_users($result->courseid , $context);
                    foreach($students as $student){
                        mtrace("User > " . $student->id);
                    }
                    
                    //mtrace("Rodou");
                    //mtrace("Total de alunos " . "x");
                    //mtrace("(dividido por 4) Seriam criados " . "x" . " grupos.");
                    //mtrace("(dividido por 6) Seriam criados " . "x" . " grupos.");
                }
                
                // Criar um tópico para cada aluno
                // 4
                
                // Remove do aluno o direito de criar tópico em fóruns
                if($result->action == 9){
                    //$DB->delete_records("role_capabilities",array('roleid'=>"5", "capability"=>"mod/forum:startdiscussion", "permission"=>1));
                }
                
                // Concede ao aluno o direito de criar tópico em fóruns
                if($result->action == 10){
                    //$DB->insert_record("role_capabilities",array('contextid'=>1, 'roleid'=>"5", "timemodified"=> $timestamp, "capability"=>"mod/forum:startdiscussion", "permission"=>1, "modifierid"=>0));
                }
            }
            catch(\Exception $e) {
                mtrace("... REPORT FAILED " . $e->getMessage());
            }
        }
        
    }
}
