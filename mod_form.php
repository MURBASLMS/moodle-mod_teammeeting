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
 * Module form.
 *
 * @package   mod_teammeeting
 * @copyright 2020 Université Clermont Auvergne
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use mod_teammeeting\helper;
use mod_teammeeting\manager;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/teammeeting/lib.php');

/**
 * Module form.
 *
 * @package   mod_teammeeting
 * @copyright 2020 Université Clermont Auvergne
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_teammeeting_mod_form extends moodleform_mod {

    /**
     * Form construction.
     */
    public function definition() {
        global $USER, $OUTPUT;
        $mform = $this->_form;

        $manager = manager::get_instance();

        if (!$manager->is_available()) {
            $this->standard_hidden_coursemodule_elements();
            $notif = new notification(get_string('apinotconfigured', 'mod_teammeeting'), notification::NOTIFY_ERROR);
            $notif->set_show_closebutton(false);
            $mform->addElement('html', $OUTPUT->render($notif));
            $mform->addElement('cancel', '', get_string('back', 'teammeeting'));
            return;
        } else if (!$manager->is_o365_user($USER->id)) {
            $this->standard_hidden_coursemodule_elements();
            $notif = new notification(get_string('noto365usercurrent', 'mod_teammeeting'), notification::NOTIFY_ERROR);
            $notif->set_show_closebutton(false);
            $mform->addElement('html', $OUTPUT->render($notif));
            $mform->addElement('cancel', '', get_string('back', 'teammeeting'));
            return;
        }

        $isedit = !empty($this->current) && !empty($this->current->id);

        $mform->addElement('header', 'general', get_string('general'));

        $mform->addElement('text', 'name', get_string('name', 'core'), ['size' => '64']);
        $mform->addRule('name', get_string('maximumchars', 'core', 255), 'maxlength', 255, 'client');
        $mform->setType('name', PARAM_TEXT);

        $this->standard_intro_elements();

        $mform->addElement('select', 'reusemeeting', get_string('reusemeeting', 'mod_teammeeting'), [
            1 => get_string('reusemeetingyes', 'mod_teammeeting'),
            0 => get_string('reusemeetingno', 'mod_teammeeting'),
        ]);
        $mform->addHelpButton('reusemeeting', 'reusemeeting', 'mod_teammeeting');
        $mform->setDefault('reusemeeting', 1);

        // Disable the reusability when we edit the resource because we cannot update
        // the meeting type (isBroadcast) once it has been created.
        if ($isedit) {
            $mform->freeze('reusemeeting');
            $mform->setConstant('reusemeeting', $this->current->reusemeeting);
            $mform->disabledIf('reusemeeting', 'team_id', 'neq', '');
        }

        // The date selectors.
        $tz = core_date::get_user_timezone_object();
        $defaultduration = (int) get_config('mod_teammeeting', 'meetingdefaultduration');
        $nowplusone = (new DateTimeImmutable('now', $tz))->add(new DateInterval('PT1H'));
        $defaultopen = $nowplusone->setTime($nowplusone->format('H'), 0, 0, 0);
        $defaultclose = $defaultopen->add(new DateInterval("PT{$defaultduration}S"));

        $mform->addElement('date_time_selector', 'opendate', get_string('opendate', 'mod_teammeeting'), [
            'defaulttime' => $defaultopen->getTimestamp()
        ]);
        $mform->addHelpButton('opendate', 'opendate', 'mod_teammeeting');
        $mform->addElement('date_time_selector', 'closedate', get_string('closedate', 'mod_teammeeting'), [
            'optional' => true,
            'defaulttime' => $defaultclose->getTimestamp()
        ]);
        $mform->addHelpButton('closedate', 'closedate', 'mod_teammeeting');

        $mform->hideIf('opendate', 'reusemeeting', 'eq', 1);
        $mform->hideIf('closedate', 'reusemeeting', 'eq', 1);

        // Membership.
        $mform->addElement('select', 'attendeesmode', get_string('attendeesmode', 'mod_teammeeting'), [
            helper::ATTENDEES_NONE => get_string('attendeesmodenonedefault', 'mod_teammeeting'),
            helper::ATTENDEES_FORCED => get_string('attendeesmodeforced', 'mod_teammeeting')
        ]);
        $mform->addHelpButton('attendeesmode', 'attendeesmode', 'mod_teammeeting');

        // Membership role.
        $mform->addElement('select', 'attendeesrole', get_string('attendeesrole', 'mod_teammeeting'), [
            helper::ROLE_ATTENDEE => get_string('attendeesroleattendee', 'mod_teammeeting'),
            helper::ROLE_PRESENTER => get_string('attendeesrolepresenter', 'mod_teammeeting')
        ]);
        $mform->addHelpButton('attendeesrole', 'attendeesrole', 'mod_teammeeting');

        // Chat settings.
        $mform->addElement('select', 'allowchat', get_string('allowchat', 'mod_teammeeting'), [
            helper::CHAT_DURING_MEETING => get_string('enabledduringmeeting', 'mod_teammeeting'),
            helper::CHAT_ENABLED => get_string('alwaysenabled', 'mod_teammeeting')
        ]);
        $mform->addHelpButton('allowchat', 'allowchat', 'mod_teammeeting');

        // Group restriction.
        $groups = ['' => get_string('noneselected', 'mod_teammeeting')] + array_map(function($group) {
            return $group->name;
        }, groups_get_all_groups($this->get_course()->id));
        $mform->addElement('autocomplete', 'groupid', get_string('restrictedtogroup', 'mod_teammeeting'), $groups, [
            'noselectionstring' => get_string('noneselected', 'mod_teammeeting')
        ]);
        $mform->addHelpButton('groupid', 'restrictedtogroup', 'mod_teammeeting');
        $mform->setDefault('groupid', '');
        $mform->disabledIf('groupmode', 'groupid', 'neq', '');
        $mform->hideIf('groupingid', 'groupid', 'neq', '');

        $this->standard_coursemodule_elements();

        // Upon activity creation, we remove "Save and display" to avoid
        // for someone to nominate themselves right away on accident.
        $this->add_action_buttons(true, empty($this->current->id) ? false : null);

        // That button is adding confusion, and it's not required for us because
        // we emulate its behaviour via our groupid field.
        if ($mform->elementExists('restrictgroupbutton')) {
            $mform->removeElement('restrictgroupbutton');
        }
    }

    /**
     * Form validation.
     *
     * @param array $data The data.
     * @param array $files The files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $isedit = !empty($this->current->id);
        $haschangeddates = !$isedit || ($this->current->opendate != $data['opendate']
            || $this->current->closedate != $data['closedate']);

        if (!$data['reusemeeting']) {
            if ($haschangeddates && (!empty($data['closedate']) && $data['closedate'] < time())) {
                // Only validate this on new, or edits where dates have changed, otherwise
                // we wouldn't be able to edit an instance that has been finished.
                $errors['closedate'] = get_string('errordatespast', 'mod_teammeeting');
            }
            if (!empty($data['closedate']) && $data['opendate'] >= $data['closedate']) {
                $errors['closedate'] = get_string('errordates', 'mod_teammeeting');
            }
        }

        return $errors;
    }

    /**
     * Fix default values.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_postprocessing($defaultvalues);

        // Upon creation of a new instance, if the course does not enforce
        // the group mode, we default to using "No groups" for simplicity.
        if (empty($this->current->id) && !$this->get_course()->groupmodeforce) {
            $defaultvalues['groupmode'] = NOGROUPS;
        }
    }

    /**
     * Post process the data.
     *
     * @param stdClass $data The form data.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        $ispermanent = !empty($data->reusemeeting);
        $defaultduration = (int) get_config('mod_teammeeting', 'meetingdefaultduration');

        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionentriesenabled) || !$autocompletion) {
                $data->completionentries = 0;
            }
        }

        if ($ispermanent) {
            // A permanent meeting should not have any dates.
            $data->opendate = 0;
            $data->closedate = 0;

        } else if (!$ispermanent && !$data->closedate) {
            // A non-permanent meeting should be given a close date if none.
            $endtime = (new DateTimeImmutable('@' . $data->opendate))->add(new DateInterval("PT{$defaultduration}S"));
            $data->closedate = $endtime->getTimestamp();
        }

        // Convert empty strings to 0.
        $data->groupid = (int) $data->groupid;

        // When our group ID is used, we hardcode other settings.
        if ($data->groupid > 0) {
            // We set to separate groups to trigger the various validation that is expected based on
            // groups. More specifically, to access the activity the user should either belong to
            // the group, or has the capability to access all groups. We do that even though we
            // will set a restrict access rule preventing other groups from accessing the activity.
            $data->groupmode = SEPARATEGROUPS;
            $data->groupingid = 0;
            $data->availabilityconditionsjson = $this->construct_availability_conditions_json($data);
        }

    }

    /**
     * Construct the availability conditions JSON.
     *
     * This attempts to automatically add a restrict access condition based on the group
     * that was set (if any). This will not inject itself in complex restrict access
     * condition trees, and will silently handle failures.
     *
     * @param object $data The submitted data.
     */
    protected function construct_availability_conditions_json($data) {
        global $CFG;

        $origvalue = !empty($data->availabilityconditionsjson) ? $data->availabilityconditionsjson : '';
        if (empty($CFG->enableavailability)) {
            return $origvalue;
        } else if (empty($data->groupid)) {
            return $origvalue;
        }

        $groupconditionenabled = array_key_exists('group', \core\plugininfo\availability::get_enabled_plugins());
        if (!$groupconditionenabled) {
            return $origvalue;
        }

        // The basic structure with just our condition.
        $structure = (object) [
            'op' => '&',
            'c' => [(object) [
                'type' => 'group',
                'id' => $data->groupid,
            ]],
            'showc' => [false],
        ];

        // If we received a value, let's see.
        if (!empty($origvalue)) {
            $origstructure = json_decode($origvalue);
            $tree = new \core_availability\tree($origstructure);

            // If the tree is not empty, let's add our condition to it there is no group
            // condition in there yet. If there is a group condition, or if the operator
            // is not a plain and simple AND, we do not do anything.
            if (!$tree->is_empty()) {
                $structure = clone $origstructure; // First, copy and assume we're not changing anything.

                // Check if we find a group condition at the top level, and if yes apply our group ID.
                $hasgroup = false;
                foreach ($structure->c as $condition) {
                    if (!empty($condition->type) && $condition->type === 'group') {
                        $condition->id = $data->groupid; // Override in case our group ID changed.
                        $hasgroup = true;
                    }
                }

                // If we have not found our group condition, let's add it.
                if (!$hasgroup && $structure->op === '&') {
                    $structure->c[] = (object) [
                        'type' => 'group',
                        'id' => $data->groupid,
                    ];
                    $structure->showc[] = false;
                } else if (!$hasgroup) {
                    debugging('Restrict access structure too advanced to check or modify.', DEBUG_DEVELOPER);
                }
            }
        }

        // Finally, validate the structure, or fallback on the original.
        try {
            new \core_availability\tree($structure);
        } catch (Exception $e) {
            debugging('Error in generated restrict access tree, reverting to original.', DEBUG_DEVELOPER);
            return $origvalue;
        }

        return json_encode($structure);
    }

}
