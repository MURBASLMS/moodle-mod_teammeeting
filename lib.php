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
 * Mandatory public API of teams module.
 *
 * @package    mod_teams
 * @copyright  2020 Université Clermont Auvergne
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_teams\manager;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/teams/classes/Office365.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/resourcelib.php');

/**
 * List of features supported in Folder module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function teams_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return false;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        default: return null;
    }
}

/**
 * Add teams instance.
 *
 * @param object $data The form data.
 * @param object $mform The form.
 * @return int The new teams instance id.
 */
function teams_add_instance($data, $mform) {
    global $DB, $USER, $COURSE;

    $context = context_course::instance($COURSE->id);
    $manager = manager::get_instance();
    $manager->require_is_available();
    $manager->require_is_o365_user($USER->id);

    // Fixing display options.
    $data->display = RESOURCELIB_DISPLAY_NEW;
    $data->displayoptions = serialize([]);

    $data->name = $data->name;
    $data->intro = $data->intro;
    $data->introformat = $data->introformat;
    $data->timemodified = time();
    $data->population = $data->type == manager::TYPE_TEAM ? $data->population : "meeting";
    $data->enrol_managers = false;
    $data->other_owners = null;

    // Creating the meeting at Microsoft.
    $o365user = $manager->get_o365_user($USER->id);
    $api = $manager->get_api();
    $meetingdata = [
        'allowedPresenters' => 'organizer',
        'autoAdmittedUsers' => 'everyone',
        'lobbyBypassSettings' => [
            'scope' => 'everyone',
            'isDialInBypassEnabled' => true
        ],
        'participants' => [
            'organizer' => [
                'identity' => [
                    'user' => [
                        'id' => $o365user->objectid,
                    ],
                ],
                'upn' => $o365user->upn,
                'role' => 'presenter',
            ],
        ],
        'subject' => format_string($data->name, true, ['context' => $context])
    ];
    if (!$data->reuse_meeting) {
        $meetingdata = array_merge($meetingdata, [
            'startDateTime' => (new DateTimeImmutable("@{$data->opendate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'endDateTime' => (new DateTimeImmutable("@{$data->closedate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'isBroadcast' => true,
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

    // Creating the activity.
    $data->resource_teams_id = $meetingid;
    $data->externalurl = $joinurl;
    $data->creator_id = $USER->id;
    $data->id = $DB->insert_record('teams', $data);

    // Send meeting link to the creator.
    if ((bool) get_config('mod_teams', 'notif_mail')) {
        $content = markdown_to_html(get_string('create_mail_content', 'mod_teams', [
            'name' => format_string($data->name, true, ['context' => $context]),
            'course' => format_string($COURSE->fullname, true, ['context' => $context]),
            'url' => $joinurl,
        ]));

        // Creation notification.
        $message = new \core\message\message();
        $message->courseid = $COURSE->id;
        $message->component = 'mod_teams';
        $message->name = 'meetingconfirm';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $USER;
        $message->subject = get_string('create_mail_title', 'mod_teams');
        $message->fullmessage = html_to_text($content);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $content;
        $message->smallmessage = get_string('create_mail_title', 'mod_teams');
        $message->notification = 1;
        message_send($message);
    }

    // Create the calendar events.
    teams_set_events($data);

    return $data->id;
}

/**
 * Update teams instance.
 *
 * @param object $data the form data.
 * @param object $mform the form.
 * @return bool true if update ok and false in other cases.
 */
function teams_update_instance($data, $mform) {
    global $DB, $COURSE;

    $context = context_course::instance($COURSE->id);
    $manager = manager::get_instance();
    $manager->require_is_available();

    $data->name = $data->name;
    $data->intro = $data->intro;
    $data->introformat = $data->introformat;
    $data->timemodified = time();

    $team = $DB->get_record('teams', ['id' => $data->instance]);
    $requiresupdate = $team->opendate != $data->opendate || $team->closedate != $data->closedate || $team->name != $data->name;

    if ($requiresupdate) {
        $manager->require_is_o365_user($team->creator_id);

        // Updating the meeting at Microsoft.
        $o365user = $manager->get_o365_user($team->creator_id);
        $api = $manager->get_api();
        $meetingdata = [
            'subject' => format_string($data->name, true, ['context' => $context])
        ];
        if (!$data->reuse_meeting) {
            $meetingdata = array_merge($meetingdata, [
                'startDateTime' => (new DateTimeImmutable("@{$data->opendate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => (new DateTimeImmutable("@{$data->closedate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            ]);
        }

        $meetingid = $team->resource_teams_id;
        $resp = $api->apicall('PATCH', "/users/{$o365user->objectid}/onlineMeetings/{$meetingid}", json_encode($meetingdata));
        $result = $api->process_apicall_response($resp, [
            'id' => null,
            'startDateTime' => null,
            'endDateTime' => null,
            'joinWebUrl' => null,
        ]);
    }

    $data->id = $data->instance;
    $DB->update_record('teams', $data);

    // Update the calendar events.
    teams_set_events($data);

    return true;
}

/**
 * Delete teams instance.
 *
 * @param int $id The id of the teams instance to delete.
 * @return bool true.
 */
function teams_delete_instance($id) {
    global $DB;

    if (!$team = $DB->get_record('teams', array('id' => $id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('teams', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'teams', $id, null);

    // All context, files, calendar events, etc... are deleted automatically.
    $DB->delete_records('teams', array('id' => $team->id));

    // Attempt to delete at Microsoft.
    $manager = manager::get_instance();
    if ($manager->is_available() && $manager->is_o365_user($team->creator_id)) {
        $o365user = $manager->get_o365_user($team->creator_id);
        $api = $manager->get_api();
        $meetingid = $team->resource_teams_id;
        $api->apicall('DELETE', "/users/{$o365user->objectid}/onlineMeetings/{$meetingid}");
    }

    return true;
}

/**
 * Get the course module info.
 *
 * @param cm_info $coursemodule The course module.
 * @return cached_cm_info The info.
 */
function teams_get_coursemodule_info($coursemodule) {
    global $DB;

    if (!$resource = $DB->get_record('teams', ['id' => $coursemodule->instance], '*')) {
        return null;
    }

    $fullurl = new moodle_url('/mod/teams/view.php', ['id' => $coursemodule->id, 'redirect' => 1]);
    $info = new cached_cm_info();
    $info->name = $resource->name;
    $info->onclick = "window.open('{$fullurl->out(false)}'); return false;";

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('teams', $resource, $coursemodule->id, false);
    }

    $info->customdata = ['fullurl' => $resource->externalurl];

    return $info;
}

/**
 * Add calendar events for the meeting.
 *
 * @param $team The team data.
 */
function teams_set_events($team) {
    global $DB;

    if ($events = $DB->get_records('event', array('modulename' => 'teams', 'instance' => $team->id))) {
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
    $event->modulename = 'teams';
    $event->instance = $team->id;
    $event->eventtype = 'open';
    $event->timestart = $team->opendate;
    $event->visible = instance_is_visible('teams', $team);
    $event->timeduration = ($team->closedate - $team->opendate);
    calendar_event::create($event);
}

/**
 * Prints information about the availability of the online meeting.
 *
 * @param object $team The teams instance.
 * @param string $format The format ('html' by default, 'text' can be used for notification).
 * @return string The information about the meeting.
 * @throws coding_exception
 */
function teams_print_details_dates($team, $format = 'html') {
    global $OUTPUT;
    if ($team->type == manager::TYPE_MEETING && !$team->reuse_meeting) {
        $msg = get_string('meetingavailablebetween', 'mod_teams', [
            'from' => userdate($team->opendate, get_string('strftimedatetimeshort', 'core_langconfig')),
            'to' => userdate($team->closedate, get_string('strftimedatetimeshort', 'core_langconfig')),
        ]);

        if ($format != 'html') {
            return $msg;
        }

        return html_writer::div(
            $OUTPUT->pix_icon('i/info', '', 'core') .
            $msg
        );
    }

    return '';
}