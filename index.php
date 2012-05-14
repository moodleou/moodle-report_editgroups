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
 * Script for editing group settings throughout a course.
 *
 * @package   report_editgroups
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(dirname(__FILE__) . '/form.php');

$id = required_param('id', PARAM_INT);       // course id
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

// needed to setup proper $COURSE
require_login($course);
//setting page url
$PAGE->set_url('/report/editgroups/index.php', array('id' => $id));
//setting page layout to report
$PAGE->set_pagelayout('report');
//coursecontext instance
$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
//checking if user is capable of viewing this report in $coursecontext
require_capability('report/editgroups:view', $coursecontext);
//strings
$strgroupreport = get_string('editgroups' , 'report_editgroups');
//initializing array to contain modules in a course.
$modinfo = array();
//fetching all modules in the course
$modinfo = get_fast_modinfo($course);

//creating form instance, passed course id as parameter to action url
$mform = new report_editgroups_form(new moodle_url('index.php', array('id' => $id)),
            array('course' => $course, 'modinfo' => $modinfo));
//create the return url after form processing
$returnurl = new moodle_url('/course/view.php', array('id' => $id));
if ($mform->is_cancelled()) {            //check if form is cancelled
    //redirect to course view page if form is cancelled
    redirect($returnurl);
} else if ($mform->is_submitted()) {        //check if form is submitted
    //process if data submitted

    //fetch data from form object
    $data = $mform->get_data();
    $groupingids = array();
    $groupmodes = array();
    $groupmembersonly = array();
    //grouping id values from the $data
    if (isset($data->groupingid) && is_array($data->groupingid)) {
        $groupingids = $data->groupingid;
    }
    //groupmode values from the $data
    if (isset($data->groupmode) && is_array($data->groupmode)) {
        $groupmodes = $data->groupmode;
    }
    //group members only values from $data
    if (isset($data->groupmembersonly) && is_array($data->groupmembersonly)) {
        $groupmembersonly = $data->groupmembersonly;
    }
    //start transaction
    $transaction = $DB->start_delegated_transaction();
    //looping through all the modules in the course
    foreach ($modinfo->cms as $cmid => $cm) {
        $modulecontext = get_context_instance(CONTEXT_MODULE, $cmid);
        //update only if user can manage activities in course context
        if (has_capability('moodle/course:manageactivities', $modulecontext)) {
            //object that will be used for updating the course module
            $cmod = new stdClass();
            $cmod->id = $cmid;
            //flag to determine if any of the settings exists for this module
            $updatemod = false;
            //update grouping id
            if ($groupingids && array_key_exists($cmid, $groupingids)) {
                $cmod->groupingid = $groupingids[$cmid];
                $updatemod = true;
            }
            //update group mode setting
            //if this id exists in the array received from $mform
            if ($groupmodes && array_key_exists($cmid, $groupmodes)) {
                $cmod->groupmode = $groupmodes[$cmid];
                $updatemod = true;
            }
            //update groupmembers only
            //if this id exists in the array received from $mform
            if ($groupmembersonly && array_key_exists($cmid, $groupmembersonly)) {
                $cmod->groupmembersonly = $groupmembersonly[$cmid];
                $updatemod = true;
            } else {        //need to handle unchecked checkboxes
                $cmod->groupmembersonly = false;
                $updatemod = true;
            }
            //module should be updated only if any of it has any of the group settings
            if ($updatemod) {
                //updating $cm object in course_modules table
                $DB->update_record('course_modules', $cmod, false);
            } else {            //no group setting for this module, continue.
                continue;
            }
        }
    }
    //transaction will be committed if every thing went fine.
    $transaction->allow_commit();
    //rebuild cache after successful updations
    rebuild_course_cache($course->id);
    //redirect to course view page after updating DB
    redirect($returnurl);
}
//making log entry
add_to_log($course->id, 'course', 'report edit groups',
     "report/editgroups/index.php?id=$course->id", $course->id);
//setting page title and page heading
$PAGE->set_title($course->shortname .': '. $strgroupreport);
$PAGE->set_heading($course->fullname);
//Displaying header and heading
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($course->fullname));

//display form
$mform->display();
//display page footer
echo $OUTPUT->footer();
