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
 * Helper.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teammeeting;

use context;
use context_course;
use local_o365\utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Utils.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Make the list of attendees.
     *
     * @param context $context The context of the meeting.
     * @param int|string $organiserid The Moodle user ID of the organiser.
     */
    public static function make_attendee_list(context $context, $organiserid) {
        $manager = manager::get_instance();
        $presenterids = utils::limit_to_o365_users(
            array_keys(get_users_by_capability($context, 'mod/teammeeting:presentmeeting', 'u.id'))
        );
        return array_filter(array_map(function($userid) use ($manager, $organiserid) {
            if ($userid == $organiserid) {
                return null; // The organiser is already an attendee.
            }
            return helper::make_meeting_participant_info($manager->get_o365_user($userid), 'presenter');
        }, $presenterids));
    }

    /**
     * Make a meetingParticipantInfo object.
     *
     * @param local_o365\obj\o365user $o365user The user.
     * @param string $role The role as onlineMeetingRole.
     */
    public static function make_meeting_participant_info($o365user, $role) {
        return [
            'identity' => [
                'user' => [
                    'id' => $o365user->objectid,
                ],
            ],
            'upn' => $o365user->upn,
            'role' => $role,
        ];
    }

    /**
     * Update the attendees of a meeting.
     *
     * @param object $teammeeting The team meeting record.
     */
    public static function update_teammeeting_instance_attendees($teammeeting) {
        $courseid = $teammeeting->course;
        $context = context_course::instance($courseid);
        $manager = manager::get_instance();
        $manager->require_is_available();

        $meetingdata = [
            'participants' => [
                'attendees' => helper::make_attendee_list($context, $teammeeting->usermodified)
            ]
        ];

        $api = $manager->get_api();
        $o365user = \local_o365\obj\o365user::instance_from_muserid($teammeeting->usermodified);
        $meetingid = $teammeeting->onlinemeetingid;
        $resp = $api->apicall('PATCH', "/users/{$o365user->objectid}/onlineMeetings/{$meetingid}", json_encode($meetingdata));
        $api->process_apicall_response($resp, []);
    }
}
