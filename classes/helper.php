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

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Utils.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /** Do not set any attendees. */
    const ATTENDEES_NONE = 0;
    /** Forces students with access to be attendees of the meeting. */
    const ATTENDEES_FORCED = 1;

    /** Attendees will have the role of an attendee. */
    const ROLE_ATTENDEE = 0;
    /** Attendees will have the role of a presenter. */
    const ROLE_PRESENTER = 1;

    /** Chat always enabled. */
    const CHAT_ENABLED = 1;
    /** Chat enabled during the meeting only. */
    const CHAT_DURING_MEETING = 2;

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
        $attendeesmode = $teammeeting->attendeesmode;

        $manager = manager::get_instance();
        $manager->require_is_available();
        $manager->require_is_o365_user($organiserid);

        // Create the meeting instance.
        $o365user = $manager->get_o365_user($organiserid);
        $api = $manager->get_api();
        $meetingdata = [
            'allowedPresenters' => static::get_allowedpresenters_value($teammeeting),
            'allowMeetingChat' => static::get_allowmeetingchat_value($teammeeting),
            'autoAdmittedUsers' => 'everyone',
            'lobbyBypassSettings' => [
                'scope' => 'everyone',
                'isDialInBypassEnabled' => true
            ],
            'participants' => [
                'organizer' => helper::make_meeting_participant_info($o365user, 'presenter'),
                'attendees' => helper::make_attendee_list($context, $organiserid, $groupid, $groupmode, $attendeesmode,
                    static::get_selected_teacher_ids($teammeeting))
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
     * Delete a meeting instance.
     *
     * @param object $teammeeting The team meeting database record.
     * @param object $meeting The meeting record.
     * @return bool
     */
    public static function delete_meeting_instance($teammeeting, $meeting) {
        if (empty($meeting->onlinemeetingid)) {
            throw new \coding_exception('The meeting instance has already been created.');
        } else if (empty($meeting->organiserid)) {
            throw new \coding_exception('The organiser ID is not specified.');
        }

        $organiserid = $meeting->organiserid;
        $manager = manager::get_instance();
        $manager->require_is_o365_user($organiserid);

        $o365user = $manager->get_o365_user($organiserid);
        $api = $manager->get_api();

        // We update the onlineMeeting instance to disable the chat, and restrict everyone from bypassing
        // the lobby. This is done because the chat persists in Teams, and so deleting the onlineMeeting
        // instance does really do anything because  users can still click the "Join" button which would
        // revive the meeting, and let them through. It's unclear whether the onlineMeeting instance is
        // revived with the same settings or not, so it's best not to delete it.
        $api->apicall('PATCH', '/users/' . $o365user->objectid . '/onlineMeetings/' . $meeting->onlinemeetingid, json_encode([
            'allowMeetingChat' => 'limited',
            'lobbyBypassSettings' => [
                'scope' => 'organizer',
                'isDialInBypassEnabled' => false
            ]
        ]));
    }

    /**
     * Get the default organiser.
     *
     * The default organiser is the recommended organiser for a particular meeting/group combination.
     * The calling code can decide to enforce this value, or let the user choose a different organiser.
     *
     * @param object $teammeeting The teammeeting instance record.
     * @param int $groupid The group ID.
     * @return int|null
     */
    public static function get_default_organiser($teammeeting, $groupid) {
        return $teammeeting->organiserid ?: null;
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
     * Get the "allowedPresenters" option value.
     *
     * @link https://learn.microsoft.com/en-us/graph/api/resources/onlinemeeting?view=graph-rest-1.0#onlinemeetingpresenters-values
     * @param object $teammeeting The database record.
     * @return string One of 'onlineMeetingPresenters'
     */
    public static function get_allowedpresenters_value($teammeeting) {
        // When the attendees should be presenters, we set everyone as presenter. We may change this in
        // the future as per feedback, but for now this works because there are cases where we do not
        // force the list of attendees but may still want people joining to be presenters by default.
        // Note that when everyone is a presenter, the individual role of each attendee can be still
        // set as attendee.
        if ($teammeeting->attendeesrole == static::ROLE_PRESENTER) {
            return 'everyone';
        }
        return 'roleIsPresenter';
    }

    /**
     * Get the "allowMeetingChat" option value.
     *
     * @link https://docs.microsoft.com/en-us/graph/api/resources/onlinemeeting?view=graph-rest-1.0#meetingchatmode-values
     * @param object $teammeeting The database record.
     * @return string One of 'meetingChatMode'
     */
    public static function get_allowmeetingchat_value($teammeeting) {
        if ($teammeeting->allowchat == static::CHAT_DURING_MEETING) {
            return 'limited';
        }
        // Defaults to 'enabled' as we do not presently support 'disabled'.
        return 'enabled';
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
     * Get the IDs of selected teachers.
     *
     * @return int[] The teacher IDs.
     */
    public static function get_selected_teacher_ids($teammeeting) {
        return array_map(function($userid) {
            return (int) $userid;
        }, explode(',', $teammeeting->teacherids));
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
     * @param int $attendeesmode The ATTENDEES_* constant.
     * @param int[] $teacherids The list of teachers to assign as presenters. An empty list results in no presenters.
     */
    public static function make_attendee_list(context $context, $organiserid, $groupid = 0, $groupmode = NOGROUPS,
            $attendeesmode = self::ATTENDEES_NONE, $teacherids = []) {

        $manager = manager::get_instance();
        $skipusers = array_flip([$organiserid]);

        // Construct the list of presenters. When a group is specified and we're in the separate groups
        // mode, then the list of presenters is limited to those who belong to the group, or who can
        // access all groups. In any other case, all presenters are attendees. These are the people
        // with the presentmeeting permisssion, which means more than just being a meeting 'presenter'.
        $candidatepresenterids = !empty($teacherids) ? utils::limit_to_o365_users(array_keys(
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
        )): [];

        // Only the nominated teachers should be considered as a valid choice.
        $candidatepresenterids = array_intersect($candidatepresenterids, $teacherids);

        // We cannot have more than 10 coorganisers.
        $coorganiserids = array_slice($candidatepresenterids, 0, 10);

        // Take the rest of users and assign them as presenters.
        $presenterids = array_diff($candidatepresenterids, $coorganiserids);

        $coorganisers = array_filter(array_map(function($userid) use ($manager, $skipusers) {
            if (array_key_exists($userid, $skipusers)) {
                return null; // The user is already an attendee.
            }
            return helper::make_meeting_participant_info($manager->get_o365_user($userid), 'coorganizer');
        }, $coorganiserids));

        // Mark all coorganisers to be skipped as already considered.
        $skipusers += array_flip($coorganiserids);

        $presenters = array_filter(array_map(function($userid) use ($manager, $skipusers) {
            if (array_key_exists($userid, $skipusers)) {
                return null; // The user is already an attendee.
            }
            return helper::make_meeting_participant_info($manager->get_o365_user($userid), 'presenter');
        }, $presenterids));

        // Mark all presenters to be skipped as already considered.
        $skipusers += array_flip($presenterids);

        // Construct the list of regular attendees. When groups are used, students are only added as
        // attendess to their own groups, even when the visible groups mode is enabled. This only applies
        $attendeeids = [];
        if ($attendeesmode == static::ATTENDEES_FORCED) {
            $attendeeids = utils::limit_to_o365_users(static::get_student_ids($context, $groupid));
        }
        $attendees = array_filter(array_map(function($userid) use ($manager, $skipusers) {
            if (array_key_exists($userid, $skipusers)) {
                return null; // The user is already an attendee.
            }
            return helper::make_meeting_participant_info($manager->get_o365_user($userid), 'attendee');
        }, $attendeeids));

        // Mandatory use of array_values to drop the keys.
        $list = array_values(array_merge($coorganisers, $presenters, $attendees));

        // The list can never be empty, else the API throws an error and thus we will always include the organiser
        // as a presenter to satisfy the API. That does not seem to have any impact on the meeting itself.
        if (empty($list)) {
            $list[] = helper::make_meeting_participant_info($manager->get_o365_user($organiserid), 'presenter');
        }

        return $list;
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
        $attendeesmode = $teammeeting->attendeesmode;

        $manager = manager::get_instance();
        $manager->require_is_available();

        $meetingdata = [
            'participants' => [
                'attendees' => helper::make_attendee_list($context, $meeting->organiserid, $meeting->groupid,
                    $groupmode, $attendeesmode, static::get_selected_teacher_ids($teammeeting))
            ]
        ];

        $api = $manager->get_api();
        $o365user = \local_o365\obj\o365user::instance_from_muserid($meeting->organiserid);
        $meetingid = $meeting->onlinemeetingid;
        $resp = $api->apicall('PATCH', "/users/{$o365user->objectid}/onlineMeetings/{$meetingid}", json_encode($meetingdata));
        $api->process_apicall_response($resp, []);
    }

}
