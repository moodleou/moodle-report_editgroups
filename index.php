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

define('REPORT_EDITGROUPS_ENABLE_FILTER_THRESHOLD', 20);

$id = required_param('id', PARAM_INT); // Course id.
$activitytype = optional_param('activitytype', '', PARAM_PLUGIN);

// Should be a valid course id.
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

// Needed to setup proper $COURSE.
require_login($course);

// Setup page.
$urlparams = array('id' => $id);
if ($activitytype) {
    $urlparams['activitytype'] = $activitytype;
}
$PAGE->set_url('/report/editgroups/index.php', $urlparams);
$PAGE->set_pagelayout('admin');

// Check permissions.
$coursecontext = context_course::instance($course->id);
require_capability('report/editgroups:view', $coursecontext);

// Fetching all modules in the course.
$modinfo = get_fast_modinfo($course);
$cms = $modinfo->get_cms();

// Prepare a list of activity types used in this course, and count the number that
// might be displayed.
$activitiesdisplayed = 0;
$activitytypes = array();
foreach ($modinfo->get_sections() as $sectionnum => $section) {
    foreach ($section as $cmid) {
        $cm = $cms[$cmid];

        // Filter activities to those that are relevant to this report.
        if (!$cm->uservisible ||
                !(plugin_supports('mod', $cm->modname, FEATURE_GROUPS, true) ||
                plugin_supports('mod', $cm->modname, FEATURE_GROUPINGS, false) ||
                (plugin_supports('mod', $cm->modname, FEATURE_GROUPMEMBERSONLY, false) &&
                $CFG->enablegroupmembersonly))) {
            continue;
        }

        $activitiesdisplayed += 1;
        $activitytypes[$cm->modname] = get_string('modulename', $cm->modname);
    }
}
core_collator::asort($activitytypes);

if ($activitiesdisplayed <= REPORT_EDITGROUPS_ENABLE_FILTER_THRESHOLD) {
    $activitytypes = array('' => get_string('all')) + $activitytypes;
}

// If activity count is above the threshold, activate the filter controls.
if (!$activitytype && $activitiesdisplayed > REPORT_EDITGROUPS_ENABLE_FILTER_THRESHOLD) {
    reset($activitytypes);
    redirect(new moodle_url('/report/editgroups/index.php',
            array('id' => $id, 'activitytype' => key($activitytypes))));
}

// Creating form instance, passed course id as parameter to action url.
$baseurl = new moodle_url('/report/editgroups/index.php', array('id' => $id));
$mform = new report_editgroups_form($baseurl, array('modinfo' => $modinfo,
        'course' => $course, 'activitytype' => $activitytype));

$returnurl = new moodle_url('/course/view.php', array('id' => $id));
if ($mform->is_cancelled()) {
    // Redirect to course view page if form is cancelled.
    redirect($returnurl);

} else if ($data = $mform->get_data()) {

    $groupingids = array();
    $groupmodes = array();
    $groupmembersonly = array();
    // Grouping id values from the $data.
    if (isset($data->groupingid) && is_array($data->groupingid)) {
        $groupingids = $data->groupingid;
    }
    // Groupmode values from the $data.
    if (isset($data->groupmode) && is_array($data->groupmode)) {
        $groupmodes = $data->groupmode;
    }
    // Group members only values from $data.
    if (isset($data->groupmembersonly) && is_array($data->groupmembersonly)) {
        $groupmembersonly = $data->groupmembersonly;
    }
    // Start transaction.
    $transaction = $DB->start_delegated_transaction();
    // Looping through all the modules in the course.
    foreach ($modinfo->get_cms() as $cmid => $cm) {
        $modulecontext = context_module::instance($cmid);
        // Update only if user can manage activities in course context.
        if (has_capability('moodle/course:manageactivities', $modulecontext)) {
            // Object that will be used for updating the course module.
            $cmod = new stdClass();
            $cmod->id = $cmid;
            // Flag to determine if any of the settings exists for this module.
            $updatemod = false;
            // Update grouping id.
            if ($groupingids && array_key_exists($cmid, $groupingids)) {
                $cmod->groupingid = $groupingids[$cmid];
                $updatemod = true;
            }
            // Update group mode setting
            // if this id exists in the array received from $mform.
            if ($groupmodes && array_key_exists($cmid, $groupmodes)) {
                $cmod->groupmode = $groupmodes[$cmid];
                $updatemod = true;
            }
            // Update groupmembers only
            // if this id exists in the array received from $mform.
            if ($groupmembersonly && array_key_exists($cmid, $groupmembersonly)) {
                $cmod->groupmembersonly = $groupmembersonly[$cmid];
                $updatemod = true;
            }
            // Module should be updated only if any of it has any of the group settings.
            if ($updatemod) {
                // Updating $cm object in course_modules table.
                $DB->update_record('course_modules', $cmod, false);
            } else { // No group setting for this module, continue.
                continue;
            }
        }
    }
    // Transaction will be committed if every thing went fine.
    $transaction->allow_commit();
    // Rebuild cache after successful updations.
    rebuild_course_cache($course->id);
    redirect($PAGE->url);
}

// Prepare activity type menu.
$select = new single_select($baseurl, 'activitytype', $activitytypes, $activitytype, null, 'activitytypeform');
$select->set_label(get_string('activitytypefilter', 'report_editgroups'));
$select->set_help_icon('activitytypefilter', 'report_editgroups');

// Making log entry.
$event = \report_editgroups\event\report_viewed::create(
        array('context' => $coursecontext, 'other' => array('activitytype' => $activitytype)));
$event->trigger();

// Set page title and page heading.
$PAGE->set_title($course->shortname . ': ' . get_string('editgroups' , 'report_editgroups'));
$PAGE->set_heading($course->fullname);

// Displaying the form.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($course->fullname));

echo $OUTPUT->heading(get_string('activityfilter', 'report_editgroups'));
echo $OUTPUT->render($select);

$mform->display();

echo $OUTPUT->footer();
