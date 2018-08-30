<?php
/*class take_over_the_world extends \core\task\adhoc_task {                                                                           
    public function get_name() {
        // Shown in admin screens
    }
    public function execute() {       
        // gain 100,000,000 friends on facebook.
        // crash the stock market.
        // run for president.
        return "hell";
    }                                                                                                                               
}*/

class send_email extends \core\task\scheduled_task {      
    public function get_name() {
        // Shown in admin screens
        return "hey Cron!";
    }
                                                                     
    public function execute() {      
        report_lastaccess_cron(); 
    }

}