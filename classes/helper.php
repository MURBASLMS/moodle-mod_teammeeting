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

use calendar_event;
use coding_exception;
use context;
use context_course;
use DateTimeImmutable;
use DateTimeZone;
use local_o365\utils;
use stdClass;

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
     * Whether the user can access a group.
     *
     * @param object $teammeeting The teammeeting instance record.
     * @param int $userid The user ID.
     * @param int $groupid The group ID.
     * @return bool
     */
    public static function can_access_group($teammeeting, $userid, $groupid) {
        $groupmode = static::get_groupmode_from_teammeeting($teammeeting);
        if ($groupmode == NOGROUPS) {
            return $groupid == 0;
        }
        $cm = static::get_cm_info_from_teammeeting($teammeeting);
        $aag = has_capability('moodle/site:accessallgroups', $cm->context, $userid);

        // When in separate groups and cannot access all groups, the user can only access a group of the grouping.
        $useridfilter = ($groupmode == VISIBLEGROUPS || $aag) ? 0 : $userid;
        $groups = groups_get_all_groups($teammeeting->course, $useridfilter, $cm->groupingid, 'g.id');
        return array_key_exists($groupid, $groups);
    }

    /**
     * Create the online meeting instance.
     *
     * @param object $teammeeting The database record.
     * @param object $groupid The group ID.
     * @return object The meeting record.
     */
    public static function create_onlinemeeting_instance($teammeeting, $groupid = 0) {
        $meeting = static::get_meeting_record($teammeeting, $groupid);
        if (!empty($meeting->onlinemeetingid)) {
            throw new \coding_exception('The meeting instance has already been created.');
        } else if (empty($meeting->organiserid)) {
            throw new \coding_exception('The organiser ID is not specified.');
        }

        $organiserid = $meeting->organiserid;
        $context = context_course::instance($teammeeting->course);
        $groupmode = static::get_groupmode_from_teammeeting($teammeeting);
        $manager = manager::get_instance();
        $manager->require_is_available();
        $manager->require_is_o365_user($organiserid);

        // Create the meeting instance.
        $o365user = $manager->get_o365_user($organiserid);
        $api = $manager->get_api();
        $meetingdata = [
            'allowedPresenters' => 'roleIsPresenter',
            'autoAdmittedUsers' => 'everyone',
            'lobbyBypassSettings' => [
                'scope' => 'everyone',
                'isDialInBypassEnabled' => true
            ],
            'participants' => [
                'organizer' => helper::make_meeting_participant_info($o365user, 'presenter'),
                'attendees' => helper::make_attendee_list($context, $organiserid, $groupid, $groupmode)
            ],
            'subject' => static::generate_onlinemeeting_name($teammeeting)
        ];
        if (!$teammeeting->reusemeeting) {
            $meetingdata = array_merge($meetingdata, [
                'startDateTime' => (new DateTimeImmutable("@{$teammeeting->opendate}",
                    new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => (new DateTimeImmutable("@{$teammeeting->closedate}",
                    new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                // Disable broadcast (live) events, out-of-time access will be controlled in Moodle.
                // 'isBroadcast' => true,
            ]);
        }

        $resp = $api->apicall('POST', '/users/' . $o365user->objectid . '/onlineMeetings', json_encode($meetingdata));
        $result = $api->process_apicall_response($resp, [
            'id' => null,
            'startDateTime' => null,
            'endDateTime' => null,
            'joinWebUrl' => null,
        ]);
        $meetingid = $result['id'];
        $joinurl = $result['joinWebUrl'];

        // Save the details.
        $meeting->lastpresenterssync = time();
        $meeting->onlinemeetingid = $meetingid;
        $meeting->meetingurl = $joinurl;
        static::save_meeting_record($meeting);

        return $meeting;
    }

    /**
     * Generate an online meeting instance's name.
     *
     * @param object $teammeeting The database record.
     */
    public static function generate_onlinemeeting_name($teammeeting) {
        $context = context_course::instance($teammeeting->course);
        $subject = format_string($teammeeting->name, true, ['context' => $context]);
        if (get_config('mod_teammeeting', 'prefixonlinemeetingname')) {
            $course = get_fast_modinfo($teammeeting->course)->get_course();
            $subject = '[' . format_string($course->shortname, true, ['context' => $context]) . '] ' . $subject;
        }
        return $subject;
    }

    /**
     * Get cm_info from teammeeting.
     *
     * @param object $teammeeting The database record.
     * @return \cm_info The cm_info object.
     * @throws \coding_exception
     */
    public static function get_cm_info_from_teammeeting($teammeeting) {
        $course = get_fast_modinfo($teammeeting->course);
        $instances = $course->get_instances_of('teammeeting');
        if (!array_key_exists($teammeeting->id, $instances)) {
            throw new coding_exception('Calling get_groupmode_from_teammeeting before instance is created.');
        }
        return $instances[$teammeeting->id];
    }

    /**
     * Get the group mode from the team meeting record.
     *
     * @param object $teammeeting The database record.
     * @return int The group mode constant.
     */
    public static function get_groupmode_from_teammeeting($teammeeting) {
        $cm = static::get_cm_info_from_teammeeting($teammeeting);
        return groups_get_activity_groupmode($cm);
    }

    /**
     * Get the meeting record for an activity/group.
     *
     * If the record does not exist, this returns a stub record.
     *
     * @param object $teammeeting The database record.
     * @param int $groupid The group ID.
     */
    public static function get_meeting_record($teammeeting, $groupid = 0) {
        global $DB;
        $meeting = $DB->get_record('teammeeting_meetings', ['teammeetingid' => $teammeeting->id, 'groupid' => $groupid]);
        if (!$meeting) {
            $meeting = (object) [
                'id' => 0,
                'teammeetingid' => $teammeeting->id,
                'groupid' => $groupid,
                'organiserid' => null,
                'onlinemeetingid' => null,
                'meetingurl' => null,
                'lastpresenterssync' => 0
            ];
        }
        return $meeting;
    }

    /**
     * Get the list of students in a context.
     *
     * @param context $context The context.
     * @param int $groupid The group ID, or 0.
     */
    public static function get_student_ids(context $context, $groupid = 0) {
        $studentroleids = array_keys(get_archetype_roles('student'));
        return array_values(array_unique(array_map(function($record) {
            return $record->userid;
        }, get_role_users($studentroleids, $context, false, 'ra.id, u.id AS userid', 'u.id ASC', true, $groupid))));
    }

    /**
     * Make the list of attendees.
     *
     * @param context $context The context of the meeting.
     * @param int|string $organiserid The Moodle user ID of the organiser.
     * @param int $groupid The group ID, or 0.
     * @param int $groupmode The group mode constant.
     */
    public static function make_attendee_list(context $context, $organiserid, $groupid = 0, $groupmode = NOGROUPS) {
        $manager = manager::get_instance();
        $skipusers = array_flip([$organiserid]);

        // Construct the list of presenters. When a group is specified and we're in the separate groups
        // mode, then the list of presenters is limited to those who belong to the group, or who can
        // access all groups. In any other case, all presenters are attendees.
        $presenterids = utils::limit_to_o365_users(array_keys(
            get_users_by_capability(
                $context,
                'mod/teammeeting:presentmeeting',
                'u.id',
                '',
                '',
                '',
                $groupid && $groupmode == SEPARATEGROUPS ? [$groupid] : '',
                '',
                null,
                null,
                $groupid && $groupmode == SEPARATEGROUPS
            )
        ));
        $presenters = array_filter(array_map(function($userid) use ($manager, $skipusers) {
            if (array_key_exists($userid, $skipusers)) {
                return null; // The user is already an attendee.
            }
            return helper::make_meeting_participant_info($manager->get_o365_user($userid), 'presenter');
        }, $presenterids));

        // Mark all presenters to be skipped as already considered.
        $skipusers += array_flip($presenterids);

        // Construct the list of regular attendees. When groups are used, students are only added as
        // attendess to their own groups, even when the visible groups mode is enabled.
        $attendeeids = utils::limit_to_o365_users(static::get_student_ids($context, $groupid));
        $attendees = array_filter(array_map(function($userid) use ($manager, $skipusers) {
            if (array_key_exists($userid, $skipusers)) {
                return null; // The user is already an attendee.
            }
            return helper::make_meeting_participant_info($manager->get_o365_user($userid), 'attendee');
        }, $attendeeids));

        // Mandatory use of array_values to drop the keys.
        return array_values(array_merge($presenters, $attendees));
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
     * Save meeting record.
     *
     * Convenient method to emphasise that the meeting record is not always
     * guaranteed to exist, and thus we this method should be used.
     *
     * @param object $meeting As provided by {@link self::get_meeting_record}.
     */
    public static function save_meeting_record($meeting) {
        global $DB;
        if (empty($meeting->id)) {
            $meeting->id = $DB->insert_record('teammeeting_meetings', $meeting);
        } else {
            $DB->update_record('teammeeting_meetings', $meeting);
        }
    }

    /**
     * Update the calendar events.
     *
     * @param object $teammeeting The database record.
     */
    public static function update_teammeeting_calendar_events($teammeeting) {
        global $DB;

        // Delete all existing events.
        if ($events = $DB->get_records('event', ['modulename' => 'teammeeting', 'instance' => $teammeeting->id])) {
            foreach ($events as $event) {
                $event = calendar_event::load($event);
                $event->delete();
            }
        }

        // We do not create events when we're missing an open or close date.
        if (!$teammeeting->opendate || !$teammeeting->closedate) {
            return;
        }

        // Fetch the cm.
        $cm = helper::get_cm_info_from_teammeeting($teammeeting);

        // Retrieve the group IDs.
        $groupids = [0];
        if (!empty($teammeeting->groupid)) {
            $groupids = [$teammeeting->groupid];
        } else if ($cm->effectivegroupmode != NOGROUPS) {
            $groups = groups_get_all_groups($cm->course, 0, $cm->groupingid, 'g.id');
            $groupids = array_keys($groups);
        }

        // Create the event in each group.
        foreach ($groupids as $groupid) {
            $event = new stdClass();
            $event->name = $teammeeting->name;
            $event->description = $teammeeting->name;
            $event->courseid = $teammeeting->course;
            $event->groupid = $groupid;
            $event->userid = 0;
            $event->modulename = 'teammeeting';
            $event->instance = $teammeeting->id;
            $event->eventtype = 'open';
            $event->timestart = $teammeeting->opendate;
            $event->visible = instance_is_visible('teammeeting', $teammeeting);
            $event->timeduration = ($teammeeting->closedate - $teammeeting->opendate);
            calendar_event::create($event);
        }
    }

    /**
     * Update the attendees of a meeting.
     *
     * @param object $teammeeting The teammeeting instance record.
     * @param object $meeting The meeting record.
     */
    public static function update_teammeeting_instance_attendees($teammeeting, $meeting) {
        if (empty($meeting->onlinemeetingid)) {
            throw new \coding_exception('The meeting instance has not yet been created.');
        } else if (empty($meeting->organiserid)) {
            throw new \coding_exception('The organiser ID is not specified.');
        }

        $courseid = $teammeeting->course;
        $context = context_course::instance($courseid);
        $groupmode = static::get_groupmode_from_teammeeting($teammeeting);
        $manager = manager::get_instance();
        $manager->require_is_available();

        $meetingdata = [
            'participants' => [
                'attendees' => helper::make_attendee_list($context, $meeting->organiserid, $meeting->groupid, $groupmode)
            ]
        ];

        $api = $manager->get_api();
        $o365user = \local_o365\obj\o365user::instance_from_muserid($meeting->organiserid);
        $meetingid = $meeting->onlinemeetingid;
        $resp = $api->apicall('PATCH', "/users/{$o365user->objectid}/onlineMeetings/{$meetingid}", json_encode($meetingdata));
        $api->process_apicall_response($resp, []);
    }

}
