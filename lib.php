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
 * Plugin API.
 *
 * @package    mod_teammeeting
 * @copyright  2020 Université Clermont Auvergne
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_teammeeting\helper;
use mod_teammeeting\manager;

defined('MOODLE_INTERNAL') || die;

/**
 * Supported features.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return mixed True or false, null when unknown.
 */
function teammeeting_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Add instance.
 *
 * @param object $data The form data.
 * @param object $mform The form.
 * @return int The new instance ID.
 */
function teammeeting_add_instance($data, $mform) {
    global $DB, $USER;

    $manager = manager::get_instance();
    $manager->require_is_available();
    $manager->require_is_o365_user($USER->id);

    $data->name = $data->name;
    $data->intro = $data->intro;
    $data->introformat = $data->introformat;
    $data->groupid = $data->groupid;
    $data->timemodified = time();
    $data->usermodified = $USER->id;
    $data->id = $DB->insert_record('teammeeting', $data);

    // Create the calendar events.
    teammeeting_set_events($data);

    return $data->id;
}

/**
 * Update instance.
 *
 * @param object $data the form data.
 * @param object $mform the form.
 * @return bool Whether the update was successful.
 */
function teammeeting_update_instance($data, $mform) {
    global $DB, $USER;

    $manager = manager::get_instance();
    $manager->require_is_available();

    $context = context_course::instance($data->course);
    $groupmode = $data->groupmode;

    $data->name = $data->name;
    $data->intro = $data->intro;
    $data->introformat = $data->introformat;
    $data->groupid = $data->groupid;
    $data->usermodified = $USER->id;
    $data->timemodified = time();

    // Read current record to check what's changed.
    $team = $DB->get_record('teammeeting', ['id' => $data->instance]);
    $attendeesmodehaschanged = $team->attendeesmode != $data->attendeesmode;
    $requiresupdate = $team->opendate != $data->opendate || $team->closedate != $data->closedate || $team->name != $data->name
        || $team->allowchat != $data->allowchat || $attendeesmodehaschanged;

    // Commit the data.
    $data->id = $data->instance;
    $DB->update_record('teammeeting', $data);

    // Re-read to get up-to-date values.
    $team = $DB->get_record('teammeeting', ['id' => $team->id]);

    // Update onlineMeeting if needed.
    if ($requiresupdate) {
        $api = $manager->get_api();
        $shareddata = [
            'allowMeetingChat' => helper::get_allowmeetingchat_value($team),
            'subject' => helper::generate_onlinemeeting_name($team)
        ];
        if (!$team->reusemeeting) {
            $shareddata = array_merge($shareddata, [
                'startDateTime' => (new DateTimeImmutable("@{$team->opendate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => (new DateTimeImmutable("@{$team->closedate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            ]);
        }

        // Retrieving all meeting instances.
        $meetings = $DB->get_records_select('teammeeting_meetings',
            'teammeetingid = ? AND onlinemeetingid IS NOT NULL AND organiserid IS NOT NULL', [$team->id]);

        // Updating the meetings at Microsoft.
        foreach ($meetings as $meeting) {
            $o365user = $manager->get_o365_user($meeting->organiserid);
            $meetingdata = $shareddata;

            // The list of participants only need to be updated when we changed the attendeesmode. It is
            // otherwise periodically updated when the meeting page is viewed.
            if ($attendeesmodehaschanged) {
                $meetingdata['participants'] = [
                    'attendees' => helper::make_attendee_list($context, $meeting->organiserid, $meeting->groupid,
                        $groupmode, $data->attendeesmode)
                ];
            }

            $meetingid = $meeting->onlinemeetingid;
            $resp = $api->apicall('PATCH', "/users/{$o365user->objectid}/onlineMeetings/{$meetingid}", json_encode($meetingdata));
            $api->process_apicall_response($resp, [
                'id' => null,
                'startDateTime' => null,
                'endDateTime' => null,
                'joinWebUrl' => null,
            ]);
        }
    }

    // Update the calendar events.
    teammeeting_set_events($team);

    return true;
}

/**
 * Delete instance.
 *
 * @param int $id The id of the instance to delete.
 * @return bool true.
 */
function teammeeting_delete_instance($id) {
    global $DB;

    if (!$teammeeting = $DB->get_record('teammeeting', ['id' => $id])) {
        return false;
    }

    $manager = manager::get_instance();
    $cm = get_coursemodule_from_instance('teammeeting', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'teammeeting', $id, null);

    // Delete remote meeting instances.
    $meetings = $DB->get_records('teammeeting_meetings', ['teammeetingid' => $teammeeting->id]);
    foreach ($meetings as $meeting) {
        if (empty($meeting->organiserid) || empty($meeting->onlinemeetingid)) {
            continue;
        } else if (!$manager->is_o365_user($meeting->organiserid)) {
            continue;
        }
        try {
            helper::delete_meeting_instance($teammeeting, $meeting);
        } catch (\moodle_exception $e) {
            debugging('Exception caught: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // Delete the team meeting instance and related meetings.
    // All context, files, calendar events, etc... are deleted automatically.
    $DB->delete_records('teammeeting_meetings', ['teammeetingid' => $teammeeting->id]);
    $DB->delete_records('teammeeting', ['id' => $teammeeting->id]);

    return true;
}

/**
 * Get the course module info.
 *
 * @param cm_info $coursemodule The course module.
 * @return cached_cm_info The info.
 */
function teammeeting_get_coursemodule_info($coursemodule) {
    global $DB;

    if (!$resource = $DB->get_record('teammeeting', ['id' => $coursemodule->instance], '*')) {
        return null;
    }

    $fullurl = new moodle_url('/mod/teammeeting/view.php', ['id' => $coursemodule->id, 'redirect' => 1]);
    $info = new cached_cm_info();
    $info->name = $resource->name;
    $info->onclick = "window.open('{$fullurl->out(false)}'); return false;";

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('teammeeting', $resource, $coursemodule->id, false);
    }

    return $info;
}

/**
 * Refresh the events.
 *
 * @param object $course The course.
 * @param object $teammeeting The instance.
 * @param object $cm The cm.
 */
function teammeeting_refresh_events($course, $teammeeting, $cm) {
    helper::update_teammeeting_calendar_events($teammeeting);
}

/**
 * Add calendar events for the meeting.
 *
 * @param object $teammeeting The team data.
 */
function teammeeting_set_events($teammeeting) {
    try {
        helper::get_cm_info_from_teammeeting($teammeeting);
    } catch (coding_exception $e) {
        // At this stage, the cm_info does not exist yet, which is probably because we
        // are calling this from within the add_instance hook. We need to schedule an
        // adhoc task to take over.
        $task = new \mod_teammeeting\task\update_calendar_events();
        $task->set_custom_data(['teammeetingid' => $teammeeting->id]);
        \core\task\manager::queue_adhoc_task($task);
        return;
    }
    helper::update_teammeeting_calendar_events($teammeeting);
}

/**
 * Prints information about the availability of the online meeting.
 *
 * @param object $team The instance.
 * @param string $format The format ('html' by default, 'text' can be used for notification).
 * @return string The information about the meeting.
 */
function teammeeting_print_details_dates($team, $format = 'html') {
    global $OUTPUT;
    if (!$team->reusemeeting) {
        $msg = get_string('meetingavailablebetween', 'mod_teammeeting', [
            'from' => userdate($team->opendate, get_string('strftimedatetimeshort', 'core_langconfig')),
            'to' => userdate($team->closedate, get_string('strftimedatetimeshort', 'core_langconfig')),
        ]);

        if ($format != 'html') {
            return $msg;
        }

        return html_writer::div(
            $OUTPUT->pix_icon('i/info', '', 'core') .
            $msg,
            'my-2'
        );
    }

    return '';
}
