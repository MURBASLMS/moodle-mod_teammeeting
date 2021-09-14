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
 * Strings for component 'teams', language 'en'.
 *
 * @package   mod_teams
 * @copyright 2020 Université Clermont Auvergne
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['modulename'] = 'Teams';
$string['modulename_help'] = 'Mod which permits to create a Teams resource and to display a link to it. For now Teams resources you can add are teams and online meetings (or virtual classrooms).<br/>Please note that users and especially students must be registered with Office 365 to be able to access and use it.';
$string['modulenameplural'] = 'Teams';
$string['pluginname'] = 'Teams';
$string['pluginadministration'] = 'Teams Resource';
$string['teams:addinstance'] = 'Add a Teams Resource';
$string['teams:view'] = 'View a Teams Resource';
$string['privacy:metadata'] = 'Teams Resource plugin does not store or transmit any personal data.';
$string['name'] = 'Resource name';
$string['name_help'] = 'Resource name which will be displayed on your course page. A prefix will be added to identify the type of the ressource.';
$string['back'] = 'Return to course';
$string['opendate'] = 'Start date of the meeting';
$string['opendate_help'] = 'The meeting will be set to start at this particular point in time.';
$string['closedate'] = 'Closing date of the meeting';
$string['closedate_help'] = 'If left blank, the default duration of the meeting will apply.';
$string['errordates'] = 'The closing date must come after the start date.';
$string['errordatespast'] = 'The closing date cannot be set in the past.';
$string['meetingnotavailable'] = 'Access to this meeting is not available. {$a} In case of difficulties please contact your course manager(s).';
$string['meetingavailablebetween'] = 'The online meeting is available between {$a->from} and {$a->to}.';
$string['copy_link'] = 'Copy the meeting link to the clipboard';
$string['create_mail_content'] = 'Hello,

You have just created the Teams online meeting "{$a->name}" in your course "{$a->course}".

You can find this meeting by clicking on this link:
[{$a->url}]
';
$string['clicktoopen'] = 'Click {$a} link to open the meeting.';
$string['create_mail_title'] = 'New Teams online meeting created';
$string['messageprovider:meetingconfirm'] = 'Confirmation of the Teams online meeting creation';
$string['notif_mail'] = 'Online meeting creation notification';
$string['notif_mail_help'] = 'Send a notification after the creation of an online meeting with the link to it.';
$string['reuse_meeting'] = 'Meeting time';
$string['reuse_meeting_help'] = 'The meeting time defines whether the meeting is limited to a time slot, or is available permanently.

- **Permanent**: The meeting URL will be accessible to whoever can access this activity from the course page.</li>
- **One shot**: The meeting URL is always available to its creator. For anybody else, the meeting will only be available within the given time frame.
';
$string['reuse_meeting_no'] = 'One shot';
$string['reuse_meeting_yes'] = 'Permanent';
$string['meeting_default_duration'] = 'Default duration for the meetings';
$string['meeting_default_duration_help'] = 'The default duration to set for the meeting when a close date is not specified.';
$string['gotoresource'] = 'Go to the Teams resource';
$string['title_courseurl'] = 'Return to the course';
$string['apinotconfigured'] = 'The Microsoft API needs to be configured and enabled in the plugin local_o365. Note that this is incomptaible with the legacy API.';
$string['noto365user'] = 'Not an O365 user. Has the user linked, or logged in with, their Microsoft 365 account?';
$string['noto365usercurrent'] = 'Missing permissions. You must link, or login with, your Microsoft 365 account to continue.';
