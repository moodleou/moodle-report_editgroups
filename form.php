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
        //global variables
        global $CFG, $DB, $COURSE;
        //get the form reference
        $mform =& $this->_form;
        //fetching $modinfo from the constructor data array
        $modinfo = $this->_customdata['modinfo'];
        //fetching $course from the constructor data array
        $course = $this->_customdata['course'];
        //fetching all the sections in the course
        $sections = get_all_sections($modinfo->courseid);
        //default -1 to display header for 0th section
        $prevsecctionnum = -1;
        //groupings selector - used for normal grouping mode
        //or also when restricting access with groupmembers only
        $options = array();
        $options[0] = get_string('none');
        //fetching groupings available to this course
        if ($groupings = $DB->get_records('groupings', array('courseid'=>$COURSE->id))) {
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = format_string($grouping->name);
            }
        }
        //flags to honour course level settings
        $forcegroupmode = false;

        if ($COURSE->groupmodeforce) {        //if group mode is forced @ course level
            $forcegroupmode = true;
        }
        // for showing submit buttons
        $showactionbuttons = false;
        //cycle through all the sections in the course
        foreach ($modinfo->sections as $sectionnum => $section) {
            //var to count the number of elements in the section.
            // It will be used to remove section if it is empty
            $elementadded = 0;
            //var to store current section name
            $sectionname = '';
            //cycle through each module in a section
            foreach ($section as $cmid) {
                //fetching the course module object from the $modinfo array.
                $cm = $modinfo->cms[$cmid];
                //no need to display/continue if this module is not visible to user
                if (!$cm->uservisible) {
                    continue;
                }
                //flag to check if user has the capability to edit this module
                $ismodreadonly = false;
                //context instance of the module
                $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                //check if user has capability to edit this module
                if (!has_capability('moodle/course:manageactivities', $context)) {
                    $ismodreadonly = true;        //update flag if user is not capable
                }
                //flags to determine availabiltity of group features
                $isenabledgroups             = false;
                $isenabledgroupings         = false;
                $isenabledgroupmemebersonly = false;
                //set flag to true if module support FEATURE_GROUPS
                //default is set to true since default value for each module
                //is set to true in course/moodleform_mod.php line 78
                if (plugin_supports('mod', $cm->modname, FEATURE_GROUPS, true)) {
                    $isenabledgroups = true;
                }
                //set flag to true if module support FEATURE_GROUPINGS
                if (plugin_supports('mod', $cm->modname, FEATURE_GROUPINGS, false)) {
                    $isenabledgroupings = true;
                }
                //set flag to true if module support FEATURE_GROUPMEMBERSONLY
                if (plugin_supports('mod', $cm->modname, FEATURE_GROUPMEMBERSONLY, false)) {
                    $isenabledgroupmemebersonly = true;
                }
                //only if the module supports either of 3 possible
                //group settings then proceed further
                if ($isenabledgroups or $isenabledgroupings or
                         ($isenabledgroupmemebersonly && $CFG->enablegroupmembersonly)) {
                    //new section, create header
                    if ($prevsecctionnum != $sectionnum) {
                        $sectionname = get_section_name($course, $sections[$sectionnum]);
                        $mform->addElement('header', $sectionname, $sectionname);
                        $prevsecctionnum = $sectionnum;
                    }
                    //fetching activity name with <h3> tag.
                    $stractivityname = html_writer::tag('h3', $cm->name);
                    //activity name shall be displayed only if any group mode setting is
                    //visible for the user check if group mode is enabled or
                    //availability to group mode is enabled @ site level
                    if ($CFG->enablegroupmembersonly || $isenabledgroups) {
                        //added activity name on the form
                        $mform->addElement('static', 'modname', $stractivityname);
                    }
                    //var to store element name
                    $elname = '';
                    //if group mode is enabled for this module
                    if ($isenabledgroups) {
                        $groupoptions = array(NOGROUPS => get_string('groupsnone'),
                        SEPARATEGROUPS => get_string('groupsseparate'),
                        VISIBLEGROUPS  => get_string('groupsvisible'));

                        //create element name and append course module id to it.
                        $elname = 'groupmode['.$cm->id.']';
                        //set flag to show action buttons
                        $showactionbuttons = true;
                        //add element to the form
                        $mform->addElement('select', $elname, get_string('groupmode',
                             'group'), $groupoptions, NOGROUPS);
                        $mform->addHelpButton($elname, 'groupmode', 'group');
                         //if group mode is forced @ course level, then honour those settings
                        if ($forcegroupmode) {
                            $mform->setDefault($elname, $COURSE->groupmode);
                        } else {
                            $mform->setDefault($elname, $cm->groupmode);
                        }
                        /*if groupmode is forced or user is not capable
                         * to edit this setting, it should appear readonly
                         */
                        if ($forcegroupmode || $ismodreadonly) {
                            $mform->hardFreeze($elname);
                        }
                        //increment the counter since an element is added
                        $elementadded++;
                    }
                    /* display grouping option only if groupings are enabled for this module
                     * or if this activity available only to group members
                     *
                     */
                    if ($isenabledgroupings or $isenabledgroupmemebersonly) {
                        //adding element(select box for grouping) to the form
                        $elname = 'groupingid['.$cm->id.']';
                        $mform->addElement('select', $elname,
                             get_string('grouping', 'group'), $options);
                        $mform->addHelpButton($elname, 'grouping', 'group');
                        $mform->setDefault($elname, $cm->groupingid);

                        //if user is not capable to edit this setting, it should appear readonly
                        if ($ismodreadonly) {
                            $mform->hardFreeze($elname);
                        }
                        //increment the counter since an element is added
                        $elementadded++;
                    }

                    //check if group members only is enabled @ site level and module level as well
                    if ($CFG->enablegroupmembersonly && $isenabledgroupmemebersonly) {
                        //adding element(checkbox) to the form
                        $elname = 'groupmembersonly['.$cm->id.']';
                        $mform->addElement('checkbox', $elname,
                             get_string('groupmembersonly', 'group'));
                        $mform->addHelpButton($elname, 'groupmembersonly', 'group');
                        if ($cm->groupmembersonly) {
                            $mform->setDefault($elname, array('checked' => 'checked'));
                        }

                        //if user is not capable to edit this setting, it should appear readonly
                        if ($ismodreadonly) {
                            $mform->hardFreeze($elname);
                        }
                        //increment the counter since an element is added
                        $elementadded++;
                    }
                    /* if group mode is enabled and available to
                     * group members does not exist for this module,
                     * then the grouping selector should be disabled by default
                     */
                    if ($mform->elementExists('groupmode['.$cmid.']')
                        and !$mform->elementExists('groupmembersonly['.$cmid.']')
                        and !$forcegroupmode) {
                        $mform->disabledif ('groupingid['.$cmid.']',
                             'groupmode['.$cmid.']', 'eq', NOGROUPS);
                    } else if (!$mform->elementExists('groupmode['.$cmid.']')
                        and $mform->elementExists('groupmembersonly['.$cmid.']')) {
                        /* if group mode is not present and available to group
                         * members only is present,then grouping option
                         * should be disabled by default
                         */
                        $mform->disabledif ('groupingid['.$cmid.']',
                             'groupmembersonly['.$cmid.']', 'notchecked');
                    } else if (!$mform->elementExists('groupmode['.$cmid.']')
                         and !$mform->elementExists('groupmembersonly['.$cmid.']')) {
                        /*  if groupmode and available to groupmembers only does
                         *  not exist for a module, then grouping option should not exist
                         *  for that module groupings have no use without
                         *  groupmode or groupmembersonly
                         *
                         */
                        if ($mform->elementExists('groupingid['.$cmid.']')) {
                            $mform->removeElement('groupingid['.$cmid.']');
                            //decrement the counter since an element is removed
                            $elementadded--;
                        }
                    }
                }
            }
            /* if section is added and no element added in this section,
             * then remove the empty section
             *
             */
            if ($elementadded == 0 && $mform->elementExists($sectionname)) {
                $mform->removeElement($sectionname);
            }
        }

        //adding submit/cancel buttons @ the end of the form
        if ($showactionbuttons) {
            $this->add_action_buttons();
        } else {
            // <div> is used for center align the continue link
            $continue_url = new moodle_url('/course/view.php', array('id' => $course->id));
            $mform->addElement('html', "<div style=text-align:center><a href="
                .$continue_url."><b>[Continue]</b></a></div>");
        }
    }
}
