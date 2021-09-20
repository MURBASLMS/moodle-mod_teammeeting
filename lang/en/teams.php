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
 * Language strings.
 *
 * @package   mod_teams
 * @copyright 2020 Université Clermont Auvergne
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apinotconfigured'] = 'The Microsoft API needs to be configured and enabled in the plugin local_o365. Note that this is incomptaible with the legacy API.';
$string['back'] = 'Return to course';
$string['clicktoopen'] = 'Click {$a} link to open the meeting.';
$string['closedate_help'] = 'If left blank, the default duration of the meeting will apply.';
$string['closedate'] = 'Closing date of the meeting';
$string['copylink'] = 'Copy the meeting link to the clipboard';
$string['errordates'] = 'The closing date must come after the start date.';
$string['errordatespast'] = 'The closing date cannot be set in the past.';
$string['gotoresource'] = 'Go to the meeting';
$string['meetingdefaultduration_help'] = 'The default duration to set for the meeting when a close date is not specified.';
$string['meetingdefaultduration'] = 'Default duration for the meetings';
$string['meetingavailablebetween'] = 'The online meeting is available between {$a->from} and {$a->to}.';
$string['meetingnotavailable'] = 'Access to this meeting is not available. {$a} In case of difficulties please contact your course manager(s).';
$string['modulename_help'] = 'This module creates online meetings with Microsoft Teams.';
$string['modulename'] = 'Teams';
$string['modulenameplural'] = 'Teams';
$string['noto365user'] = 'Not an O365 user. Has the user linked, or logged in with, their Microsoft 365 account?';
$string['noto365usercurrent'] = 'Missing permissions. You must link, or login with, your Microsoft 365 account to continue.';
$string['opendate_help'] = 'The meeting will be set to start at this particular point in time.';
$string['opendate'] = 'Start date of the meeting';
$string['pluginadministration'] = 'Teams administrations';
$string['pluginname'] = 'Teams';
$string['privacy:metadata'] = 'The plugin does not store or transmit any personal data.';
$string['returntocourse'] = 'Return to the course';
$string['reusemeeting_help'] = 'The meeting time defines whether the meeting is limited to a time slot, or is available permanently.

- **Open ended**: The meeting URL will be accessible to whoever can access this activity from the course page.</li>
- **Time slot**: The meeting URL is always available to its creator. For anybody else, the meeting will only be available within the given time frame.
';
$string['reusemeetingno'] = 'Time slot';
$string['reusemeetingyes'] = 'Open ended';
$string['reusemeeting'] = 'Meeting time';
$string['teams:addinstance'] = 'Add a Teams instance';
$string['teams:view'] = 'View a Teams instance';
