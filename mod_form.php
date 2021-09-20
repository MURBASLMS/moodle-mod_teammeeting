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

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
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
    }

}
