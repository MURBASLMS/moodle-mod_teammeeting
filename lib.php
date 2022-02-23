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

use mod_teammeeting\manager;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Supported features.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return mixed True or false, null when unknown.
 */
function teammeeting_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
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
    global $DB, $USER, $COURSE;

    $context = context_course::instance($COURSE->id);
    $manager = manager::get_instance();
    $manager->require_is_available();
    $manager->require_is_o365_user($USER->id);

    $data->name = $data->name;
    $data->intro = $data->intro;
    $data->introformat = $data->introformat;
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
    global $DB, $COURSE, $USER;

    $context = context_course::instance($COURSE->id);
    $manager = manager::get_instance();
    $manager->require_is_available();

    $data->name = $data->name;
    $data->intro = $data->intro;
    $data->introformat = $data->introformat;
    $data->usermodified = $USER->id;
    $data->timemodified = time();

    $team = $DB->get_record('teammeeting', ['id' => $data->instance]);
    $requiresupdate = $team->opendate != $data->opendate || $team->closedate != $data->closedate || $team->name != $data->name;

    if ($requiresupdate) {

        $api = $manager->get_api();
        $meetingdata = ['subject' => format_string($data->name, true, ['context' => $context])];
        if (!$data->reusemeeting) {
            $meetingdata = array_merge($meetingdata, [
                'startDateTime' => (new DateTimeImmutable("@{$data->opendate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => (new DateTimeImmutable("@{$data->closedate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            ]);
        }

        // Retrieving all meeting instances.
        $meetings = $DB->get_records_select('teammeeting_meetings',
            'teammeetingid = ? AND onlinemeetingid IS NOT NULL AND organiserid IS NOT NULL', [$team->id]);

        // Updating the meetings at Microsoft.
        foreach ($meetings as $meeting) {
            $o365user = $manager->get_o365_user($meeting->organiserid);

            // Note that attendees (presenters) do not need updating, they are periodically updated when the meeting page is viewed.
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


    $data->id = $data->instance;
    $DB->update_record('teammeeting', $data);

    // Update the calendar events.
    teammeeting_set_events($data);

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

    if (!$team = $DB->get_record('teammeeting', array('id' => $id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('teammeeting', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'teammeeting', $id, null);

    // All context, files, calendar events, etc... are deleted automatically.
    $DB->delete_records('teammeeting', array('id' => $team->id));

    // Attempt to delete at Microsoft. This is temporarily disabled because it currently
    // conflicts with the ability to duplicate an activity. If an activity is duplicated, and
    // either copies are deleted, the meeting link will be invalidated for both. The better
    // strategy would be to create a new meeting upon restore but that has broader implications.
    if (false) {
        $manager = manager::get_instance();
        if ($manager->is_available() && $manager->is_o365_user($team->organiserid)) {
            $o365user = $manager->get_o365_user($team->organiserid);
            $api = $manager->get_api();
            $meetingid = $team->onlinemeetingid;
            $api->apicall('DELETE', "/users/{$o365user->objectid}/onlineMeetings/{$meetingid}");
        }
    }

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
 * Add calendar events for the meeting.
 *
 * @param object $team The team data.
 */
function teammeeting_set_events($team) {
    global $DB;

    if ($events = $DB->get_records('event', array('modulename' => 'teammeeting', 'instance' => $team->id))) {
        foreach ($events as $event) {
            $event = calendar_event::load($event);
            $event->delete();
        }
    }

    // We do not create events when we're missing an open or close date.
    if (!$team->opendate || !$team->closedate) {
        return;
    }

    // The open-event.
    $event = new stdClass;
    $event->name = $team->name;
    $event->description = $team->name;
    $event->courseid = $team->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->modulename = 'teammeeting';
    $event->instance = $team->id;
    $event->eventtype = 'open';
    $event->timestart = $team->opendate;
    $event->visible = instance_is_visible('teammeeting', $team);
    $event->timeduration = ($team->closedate - $team->opendate);
    calendar_event::create($event);
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
