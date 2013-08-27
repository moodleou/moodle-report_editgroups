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
 * report_editgroups form definition.
 *
 * @package   report_editgroups
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');


/**
 * The form for editing the group settings.
 *
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_editgroups_form extends moodleform {

    public function definition() {
        global $CFG, $COURSE, $DB;
        $mform = $this->_form;

        $modinfo       = $this->_customdata['modinfo'];
        $course        = $this->_customdata['course'];
        $activitytype  = $this->_customdata['activitytype'];

        // Context instance of the course.
        $coursecontext = context_course::instance($course->id);

        // Groupings selector - used for normal grouping mode
        // or also when restricting access with groupmembers only.
        $options = array();
        $options[0] = get_string('none');
        // Fetching groupings available to this course.
        if ($groupings = $DB->get_records('groupings', array('courseid'=>$COURSE->id))) {
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = format_string($grouping->name);
            }
        }
        // Flags to honour course level settings.
        $forcegroupmode = false;

        // If group mode is forced @ course level.
        if ($COURSE->groupmodeforce) {
            $forcegroupmode = true;
        }
        // For showing submit buttons.
        $showactionbuttons = false;

        // Store current activity type.
        $mform->addElement('hidden', 'activitytype', $activitytype);
        $mform->setType('activitytype', PARAM_PLUGIN);

        // Add save action button to the top of the form.
        $this->add_action_buttons();

        // Default -1 to display header for 0th section.
        $prevsectionnum = -1;

        // Cycle through all the sections in the course.
        $cms = $modinfo->get_cms();
        foreach ($modinfo->get_sections() as $sectionnum => $section) {
            // Var to count the number of elements in the section.
            // It will be used to remove section if it is empty.
            $elementadded = 0;
            // Var to store current section name.
            $sectionname = '';
            // Cycle through each module in a section.
            foreach ($section as $cmid) {
                $cm = $cms[$cmid];

                // No need to display/continue if this module is not visible to user.
                if (!$cm->uservisible) {
                    continue;
                }

                // If activity filter is on, then filter module by activity type.
                if ($activitytype && $cm->modname != $activitytype) {
                    continue;
                }

                // Check if the user has capability to edit this module settings.
                $modcontext = context_module::instance($cm->id);
                $ismodreadonly = !has_capability('moodle/course:manageactivities', $modcontext);

                // Flags to determine availabiltity of group features.
                $isenabledgroups            = plugin_supports('mod', $cm->modname, FEATURE_GROUPS, true);
                $isenabledgroupings         = plugin_supports('mod', $cm->modname, FEATURE_GROUPINGS, false);
                $isenabledgroupmemebersonly = plugin_supports('mod', $cm->modname, FEATURE_GROUPMEMBERSONLY, false);

                // Only if the module supports either of 3 possible
                // group settings then proceed further.
                if ($isenabledgroups or $isenabledgroupings or
                         ($isenabledgroupmemebersonly && $CFG->enablegroupmembersonly)) {
                    // New section, create header.
                    if (($prevsectionnum != $sectionnum)) {
                        $sectionname = get_section_name($course, $modinfo->get_section_info($sectionnum));
                        $headername = 'section' . $sectionnum . 'header';
                        $mform->addElement('header', $headername, $sectionname);
                        $mform->setExpanded($headername, false);
                        $prevsectionnum = $sectionnum;
                    }

                    // Display activity name.
                    $iconmarkup = html_writer::empty_tag('img', array(
                            'src' => $cm->get_icon_url(), 'class' => 'activityicon', 'alt' => '' ));
                    $stractivityname = html_writer::tag('strong' , $iconmarkup . $cm->name);

                    // Activity name shall be displayed only if any group mode setting is
                    // visible for the user check if group mode is enabled or
                    // availability to group mode is enabled @ site level.
                    if ($CFG->enablegroupmembersonly || $isenabledgroups) {
                        // Added activity name on the form.
                        $mform->addElement('static', 'modname', $stractivityname);
                    }
                    // Var to store element name.
                    $elname = '';
                    // If group mode is enabled for this module.
                    if ($isenabledgroups) {
                        $groupoptions = array(NOGROUPS => get_string('groupsnone'),
                        SEPARATEGROUPS => get_string('groupsseparate'),
                        VISIBLEGROUPS  => get_string('groupsvisible'));

                        // Create element name and append course module id to it.
                        $elname = 'groupmode['.$cm->id.']';
                        // Add element to the form.
                        $mform->addElement('select', $elname, get_string('groupmode',
                                'group'), $groupoptions, NOGROUPS);
                        $mform->addHelpButton($elname, 'groupmode', 'group');
                         // If group mode is forced @ course level, then honour those settings.
                        if ($forcegroupmode) {
                            $mform->setDefault($elname, $COURSE->groupmode);
                        } else {
                            $mform->setDefault($elname, $cm->groupmode);
                        }
                        // If groupmode is forced or user is not capable
                        // to edit this setting, it should appear readonly.
                        if ($forcegroupmode || $ismodreadonly) {
                            $mform->hardFreeze($elname);
                        }
                        // Increment the counter since an element is added.
                        $elementadded++;
                    }
                    // Display grouping option only if groupings are enabled for this module
                    // or if this activity available only to group members.
                    if ($isenabledgroupings or $isenabledgroupmemebersonly) {
                        // Adding element(select box for grouping) to the form.
                        $elname = 'groupingid['.$cm->id.']';
                        $mform->addElement('select', $elname,
                                get_string('grouping', 'group'), $options);
                        $mform->addHelpButton($elname, 'grouping', 'group');
                        $mform->setDefault($elname, $cm->groupingid);

                        // If user is not capable to edit this setting, it should appear readonly.
                        if ($ismodreadonly) {
                            $mform->hardFreeze($elname);
                        }
                        // Increment the counter since an element is added.
                        $elementadded++;
                    }

                    // Check if group members only is enabled @ site level and module level as well.
                    if ($CFG->enablegroupmembersonly && $isenabledgroupmemebersonly) {
                        // Adding element(checkbox) to the form.
                        $elname = 'groupmembersonly['.$cm->id.']';
                        $mform->addElement('advcheckbox', $elname,
                                get_string('groupmembersonly', 'group'));
                        $mform->addHelpButton($elname, 'groupmembersonly', 'group');
                        if ($cm->groupmembersonly) {
                            $mform->setDefault($elname, true);
                        }

                        // If user is not capable to edit this setting, it should appear readonly.
                        if ($ismodreadonly) {
                            $mform->hardFreeze($elname);
                        }
                        // Increment the counter since an element is added.
                        $elementadded++;
                    }

                    // If group mode is enabled and available to
                    // group members does not exist for this module,
                    // then the grouping selector should be disabled by default,
                    // but only if a grouping is not already set.
                    if (!$cm->groupingid) {
                        if ($mform->elementExists('groupmode['.$cmid.']')
                                && !$mform->elementExists('groupmembersonly['.$cmid.']')
                                && !$forcegroupmode) {
                            $mform->disabledif('groupingid['.$cmid.']',
                                 'groupmode['.$cmid.']', 'eq', NOGROUPS);

                        } else if (!$mform->elementExists('groupmode['.$cmid.']')
                                && $mform->elementExists('groupmembersonly['.$cmid.']')) {
                            // If group mode is not present and available to group
                            // members only is present,then grouping option
                            // should be disabled by default.
                            $mform->disabledif('groupingid['.$cmid.']',
                                 'groupmembersonly['.$cmid.']', 'notchecked');

                        } else if (!$mform->elementExists('groupmode['.$cmid.']')
                                && !$mform->elementExists('groupmembersonly['.$cmid.']')) {
                            // If groupmode and available to groupmembers only does
                            // not exist for a module, then grouping option should not exist
                            // for that module groupings have no use without
                            // groupmode or groupmembersonly.
                            if ($mform->elementExists('groupingid['.$cmid.']')) {
                                $mform->removeElement('groupingid['.$cmid.']');
                                // Decrement the counter since an element is removed.
                                $elementadded--;
                            }
                        }
                    }
                }
            }

            // If section is added and no element added in this section,
            // then remove the empty section.
            if (($elementadded == 0) && ($sectionname != '') && $mform->elementExists($sectionname)) {
                $mform->removeElement($sectionname);
            }
            if (!$showactionbuttons && $elementadded > 0) {
                // Set flag to show action buttons.
                $showactionbuttons = true;
            }
        }

        // Adding submit/cancel buttons @ the end of the form.
        if ($showactionbuttons) {
            $this->add_action_buttons();
        } else {
            // Remove top action button.
            $mform->removeElement('buttonar');
        }
    }
}
