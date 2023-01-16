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
 * Restore steps.
 *
 * @package    mod_teammeeting
 * @copyright  2021 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_teammeeting\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Restore steps.
 *
 * @package    mod_teammeeting
 * @copyright  2021 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_teammeeting_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define structure.
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('teammeeting', '/activity/teammeeting')
        ];

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process element.
     *
     * @param array $data The data.
     */
    protected function process_teammeeting($data) {
        global $DB, $USER;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->allowchat = isset($data->allowchat) ? $data->allowchat : helper::CHAT_ENABLED;
        $data->attendeesmode = isset($data->attendeesmode) ? $data->attendeesmode : helper::ATTENDEES_FORCED;
        $data->attendeesrole = isset($data->attendeesrole) ? $data->attendeesrole : helper::ROLE_ATTENDEE;
        $data->teachersmode = isset($data->teachersmode) ? $data->teachersmode : helper::TEACHERS_ALL;
        $data->teacherids = isset($data->teacherids) ? $data->teacherids : '';
        $data->groupid = !empty($data->groupid) ? $this->get_mappingid('group', $data->groupid, 0) : 0;
        $data->usermodified = $this->get_mappingid('user', $data->usermodified, $USER->id);

        // Note that restored activities do not restore their associated online meetings.

        // Insert the new record.
        $newitemid = $DB->insert_record('teammeeting', $data);

        $this->apply_activity_instance($newitemid);
    }

    /**
     * After execute.
     */
    protected function after_execute() {
        $this->add_related_files('mod_teammeeting', 'intro', null);
    }
}
