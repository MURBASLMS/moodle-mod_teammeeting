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
 * Get meeting info.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_teammeeting\manager;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$usage = "Get a meeting's information

Usage:
    $ php get_meeting_info.php --cmid=<cmid>

Options:
    -h --help                   Print this help.
    --cmid=<cmid>               The activity's course module ID.

Examples:

    $ php get_meeting_info.php --cmid=3
        Retrieves information about the meetings for activity with course module ID 3
";

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'cmid' => null,
], [
    'h' => 'help'
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(1);
}

if ($options['cmid'] === null) {
    cli_problem('Missing cmid');
    cli_writeln($usage);
    exit(2);
}

$cmid = $options['cmid'];
$cm = get_coursemodule_from_id('teammeeting', $cmid, 0, false, MUST_EXIST);
$teammeeting = $DB->get_record('teammeeting', ['id' => $cm->instance], '*', MUST_EXIST);
$manager = manager::get_instance();
$api = $manager->get_api();

cli_writeln('Name: ' . $teammeeting->name);
cli_writeln('----------');
cli_writeln('CM ID: ' . $cm->id);
cli_writeln('Instance ID: ' . $teammeeting->id);
cli_writeln('Course ID: ' . $cm->course);
cli_writeln('----------');
cli_writeln("Forced group: " . mod_teammeeting_cli_get_group($teammeeting->groupid));
$meetings = $DB->get_records('teammeeting_meetings', ['teammeetingid' => $teammeeting->id]);
cli_writeln('Online meetings: ' . count($meetings));
cli_writeln('');
cli_writeln('==========');
cli_writeln('');

foreach ($meetings as $meeting) {
    cli_writeln("MEETING {$meeting->id}");
    cli_writeln('----------');
    cli_writeln('Group: ' . mod_teammeeting_cli_get_group($meeting->groupid));

    $organiser = core_user::get_user($meeting->organiserid);
    cli_writeln("Organiser: {$meeting->organiserid} (" . ($organiser ? fullname($organiser) : '?') . ")");
    cli_writeln("OnlineMeeting ID: {$meeting->onlinemeetingid}");
    cli_writeln("Meeting URL: {$meeting->meetingurl}");

    if (!empty($meeting->organiserid) && !empty($meeting->onlinemeetingid)) {
        $o365user = $manager->get_o365_user($meeting->organiserid);
        $resp = $api->apicall('GET', "/users/{$o365user->objectid}/onlineMeetings/{$meeting->onlinemeetingid}");
        $result = $api->process_apicall_response($resp, [
            'id' => null,
            'participants' => null,
            'subject' => null,
        ]);

        $attendee = $result['participants']['organizer'];
        cli_writeln("Name (in onlineMeeting): {$result['subject']}");
        cli_writeln("Organiser (in onlineMeeting): {$attendee['upn']}");
        cli_writeln("Attendees (in onlineMeeting):");
        if (!empty($result['participants']['attendees'])) {
            foreach ($result['participants']['attendees'] as $attendee) {
                cli_writeln(" - [{$attendee['role']}] {$attendee['upn']}");
            }
        } else {
            cli_writeln(" - None");
        }
    }
    cli_writeln('');
}


function mod_teammeeting_cli_get_group($groupid) {
    global $DB;

    if (!$groupid) {
        return 'None';
    }
    $group = $DB->get_record('groups', ['id' => $groupid]);
    $name = ($group ? $group->name : '?');
    return "{$groupid} ({$name})";
}