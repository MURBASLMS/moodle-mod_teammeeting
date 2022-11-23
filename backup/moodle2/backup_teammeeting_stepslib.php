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
 * Backup steps.
 *
 * @package    mod_teammeeting
 * @copyright  2021 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup steps.
 *
 * @package    mod_teammeeting
 * @copyright  2021 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_teammeeting_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define structure.
     */
    protected function define_structure() {

        // Define each element separated.
        $teammeeting = new backup_nested_element('teammeeting', ['id'], [
            'name', 'intro', 'introformat', 'opendate', 'closedate', 'usermodified',
            'reusemeeting', 'allowchat', 'attendeesmode', 'attendeesrole', 'groupid', 'timemodified'
        ]);
        $meeting = new backup_nested_element('meeting', ['id'], ['groupid', 'organiserid', 'onlinemeetingid', 'meetingurl']);
        $teammeeting->add_child($meeting);

        // Define sources.
        $teammeeting->set_source_table('teammeeting', array('id' => backup::VAR_ACTIVITYID));
        $meeting->set_source_table('teammeeting_meetings', ['teammeetingid' => backup::VAR_PARENTID]);

        // Define ID annotations.
        $teammeeting->annotate_ids('user', 'usermodified');
        $teammeeting->annotate_ids('group', 'groupid');
        $meeting->annotate_ids('user', 'organiserid');
        $meeting->annotate_ids('group', 'groupid');

        // Define file annotations.
        $teammeeting->annotate_files('mod_teammeeting', 'intro', null);

        return $this->prepare_activity_structure($teammeeting);
    }

}
