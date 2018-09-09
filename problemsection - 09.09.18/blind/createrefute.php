<?php
global $CFG, $PAGE, $USER, $SITE, $COURSE;

//require_once("../../config.php");
require_once dirname(dirname(dirname(__FILE__))).'/../config.php';
require_once('../lib.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/group/lib.php');
require_once('rundebate_lib.php');

// Access control.
$courseid = required_param('id', PARAM_INT);
$debate = optional_param('debate', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('local/problemsection:addinstance', $context);

//$pageurl = new moodle_url('/local/problemsection/debateadm.php', array('id' => $courseid));
$course = $DB->get_record('course', array('id' => $courseid));

require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:managegroups', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code
$pageurl = new moodle_url('/local/problemsection/blind/createrefute.php', array('id' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);

$pagetitle = "Converter quiz em grupo";
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
try{
    echo "<pre>";
    $getmodulestatusid = $DB->get_record('local_problemsection_status', array('courseid'=>$courseid, 'problemid'=>$debate));
    //print_r($getmodulestatusid);

    if($getmodulestatusid->forumdiscussionid <= 0){
        header("Location: ../manage.php?id=$courseid&action=15&debate=$debate&psid=");
    }
    else{
        // Open forum (debate) - Readyonly
        try{
            $getforumdata = $DB->get_record('course_modules', array('course'=>$courseid, 'module'=>9, 'instance'=>$getmodulestatusid->forumdiscussionid));
            $forumdata = context_module::instance($getforumdata->id);
            $DB->update_record('course_modules', array('id'=>$getforumdata->id, 'groupmode'=>2));
            // role_change_permission($roleid, $context, $capname, $permission) 
            role_change_permission(5, $forumdata, "mod/forum:replypost", -1000);
            role_change_permission(5, $forumdata, "mod/forum:deleteownpost", -1000);
            role_change_permission(5, $forumdata, "mod/forum:exportownpost", -1000);
        }catch(\Exception $e){}

        // Create new forum
        $forum = create_forum($courseid, "Forum de confrontação", $debate);

        // Load every group
        //$returnallgroups = $DB->get_records('groups', array('courseid'=>$courseid));
        $returnallgroups = explode(',', $getmodulestatusid->sequence);

        $debategroups = array();

        foreach($returnallgroups as $returnallgroup){
            $groupdata = $DB->get_record('groups', array('id'=>$returnallgroup));
            print_r($groupdata);
            if($groupdata->idnumber != ""){
                $getfirstsectionidnumber = explode(']', $groupdata->idnumber);
                $getmagicalnumber = explode('[', $getfirstsectionidnumber[0]);
                if(($getmagicalnumber[1] == 1) || ($getmagicalnumber[1] == 2)){
                    $debategroups[] = $groupdata;
                }
            }
        }

        $newpairs = array_chunk($debategroups,2);
        $createdgroups = array();

        foreach($newpairs as $newpair){
            // Names
            $firstgroupname = $newpair[0]->name;
            $secondgroupname = $newpair[1]->name;

            $newgroupname = $firstgroupname . " e " . $secondgroupname;

            //create group
            $data = new stdClass();
            $data->courseid = $courseid;
            $data->name = $newgroupname;
            $data->idnumber = "";
            $data->description = $newgroupname;
            /*$data->descriptionformat = $newgroupname;
            $data->enrolmentkey = $newgroupname;
            $data->picture = $newgroupname;
            $data->hidepicture = $newgroupname;*/
            $newgroup = groups_create_group($data);
            
            // Get users from groups
            for($i = 0; $i <= 1; $i++){
                $getusersingroups = $DB->get_records('groups_members', array("groupid"=>$newpair[$i]->id));
                //print_r($getusersingroups);

                foreach($getusersingroups as $getuseringroup){
                    //echo "rodou +1";
                    $group = groups_get_group($newgroup, 'id, courseid', MUST_EXIST);
                    $user = $DB->get_record('user', array('id'=>$getuseringroup->userid));
                    print_r(groups_add_member($group, $user));
                    //print_r($group);
                }
            }

            @$createdgroups[] = array("name" => $newgroupname, "id"=> $group->id);
        }
        //echo "<pre>";
        //print_r($createdgroups);

        // --------------------------------------
        // Create post for each new group
        //echo "<pre>";
        foreach($createdgroups as $createdgroup){
            //print_r($createdgroup);
            //echo "ID > " . $createdgroup["id"];
            $topictitleformat = array();
                $topictitleformat[1] = "(confrontação a favor)";
                $topictitleformat[2] = "(confrontação contra)";

            $discussion = new stdClass();
            $discussion->course        = $courseid;
            $discussion->forum         = $forum->id;
            $discussion->message       = $forum->intro;
            $discussion->messageformat = 1;
            $discussion->messagetrust  = trusttext_trusted(context_course::instance($courseid));
            $discussion->groupid       = $createdgroup["id"];
            $discussion->mailnow       = false;
            $message = '';

            if($createdgroup["id"] != ""){
                //for($i = 1; $i <= 2; $i++){
                    //echo $topictitleformat[$i];
                    $discussion->name          = $createdgroup["name"] . ' ' . $topictitleformat[$i];
                    $discussion->id = forum_add_discussion($discussion, null, $message);
                    //echo $discussion->name;
                    //echo "<br>|||||||||||||||||||||||||||||||||||||||||||<br>";
                //}
            }
            //echo "<br>************************************************<br>";
        }

        $DB->update_record('local_problemsection_status', array('id'=>$getmodulestatusid->id, 'forumrefuteid'=>$forum->id, 'problemid'=>$debate));

        rebuild_course_cache($courseid);

        header("Location: ../manage.php?id=$courseid&action=2&debate=$debate&psid=");
    }

}
catch(\Exception $e) {
    echo("Fail" . $e->getMessage());
}