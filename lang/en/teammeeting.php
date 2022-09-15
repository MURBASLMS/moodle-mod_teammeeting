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
 * @package   mod_teammeeting
 * @copyright 2020 Université Clermont Auvergne
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['active'] = 'Active';
$string['allowchat'] = 'Meeting chat';
$string['allowchat_help'] = 'When the chat is set to "Enabled during meeting", students can chat while the meeting is ongoing, but not once it has ended. Set this to "Always enabled" to let students use the chat after the meeting ends.';
$string['alreadyorganiserofothersession'] = 'Already the organiser of another session.';
$string['alwaysenabled'] = 'Always enabled';
$string['apinotconfigured'] = 'The Microsoft API needs to be configured and enabled in the plugin local_o365. Note that this is incomptaible with the legacy API.';
$string['attendeesmode'] = 'Membership';
$string['attendeesmode_help'] = 'By default the Teams meeting and associated group chat student membership attendees are not set, so only students who actually click the url and join a session will become members of the meeting and group chat.

Force/Set membership for a meeting if you want:

- all students in the course or students in a specific group to get notified of a session starting.
- all students in the course or students in a specific group to be able to see and join the group chat without necessarily joining a particular session.

Setting membership by a specific group also acts as a shortcut to set "Group mode" and "Restrict access", which will be set automatically. It is recommended to leave these settings in the way they are set, but note that you should manually check the values of "Group mode" and "Restrict access" when reverting this setting to "None selected".
';
$string['attendeesmodenonedefault'] = 'Upon joining (default)';
$string['attendeesmodeforced'] = 'Forced';
$string['back'] = 'Return to course';
$string['cannotaccessgroup'] = 'Cannot access group';
$string['clicktoopen'] = 'Click {$a} link to open the meeting.';
$string['closedate_help'] = 'If left blank, the default duration of the meeting will apply.';
$string['closedate'] = 'Closing date of the meeting';
$string['copylink'] = 'Copy the meeting link to the clipboard';
$string['enabledduringmeeting'] = 'Enabled during meeting';
$string['errordates'] = 'The closing date must come after the start date.';
$string['errordatespast'] = 'The closing date cannot be set in the past.';
$string['gotoresource'] = 'Go to the meeting';
$string['link'] = 'Link';
$string['lobbywaitmessage'] = 'The session will start soon, you will automatically be redirected when it is ready.';
$string['meetingdefaultduration_help'] = 'The default duration to set for the meeting when a close date is not specified.';
$string['meetingdefaultduration'] = 'Default duration for the meetings';
$string['meetingavailablebetween'] = 'The online meeting is available between {$a->from} and {$a->to}.';
$string['meetingnotavailable'] = 'Access to this meeting is not available. {$a} In case of difficulties please contact your course manager(s).';
$string['meetingurl'] = 'Meeting URL';
$string['modulename_help'] = 'This module creates online meetings with Microsoft Teams.';
$string['modulename'] = 'Team meeting';
$string['modulenameplural'] = 'Team meetings';
$string['noinstancesofplugin'] = 'There are no instances of Team meeting.';
$string['nominatemyself'] = 'Nominate myself';
$string['noneselected'] = 'None selected';
$string['noto365user'] = 'Not an O365 user. Has the user linked, or logged in with, their Microsoft 365 account?';
$string['noto365usercurrent'] = 'Missing permissions. You must link, or login with, your Microsoft 365 account to continue.';
$string['opendate_help'] = 'The meeting will be set to start at this particular point in time.';
$string['opendate'] = 'Start date of the meeting';
$string['organiser'] = 'Organiser';
$string['organiseralreadyset'] = 'The organiser has already been defined.';
$string['othergroups'] = 'Other groups';
$string['pleasewait'] = 'Please wait!';
$string['pluginadministration'] = 'Team meeting administrations';
$string['pluginname'] = 'Team meeting';
$string['prefixonlinemeetingname'] = 'Prefix online meeting name';
$string['prefixonlinemeetingname_help'] = 'When enabled, the name of the onlineMeeting instance will be prefixed with the course short name.';
$string['privacy:metadata'] = 'The plugin does not store or transmit any personal data.';
$string['restrictedtogroup'] = 'Restricted to group';
$string['restrictedtogroup_help'] = 'Restrict access to this meeting to a given group.

This settings mostly acts as a shortcut to set other settings such as the "Group mode" and "Restrict access", which will be set automatically.

This setting is also used to generate the list of attendees of the meeting.

Note that you should manually check the values of "Group mode" and "Restrict access" when reverting this setting to "None selected".';
$string['returntocourse'] = 'Return to the course';
$string['reusemeeting_help'] = 'The meeting time defines whether the meeting is limited to a time slot, or is available permanently.

- **Open ended**: The meeting URL will be accessible to whoever can access this activity from the course page.</li>
- **Time slot**: The meeting URL is always available to its creator. For anybody else, the meeting will only be available within the given time frame.
';
$string['reusemeetingno'] = 'Time slot';
$string['reusemeetingyes'] = 'Open ended';
$string['reusemeeting'] = 'Meeting time';
$string['selectgroupformeeting'] = 'Please select the group to open the meeting for.';
$string['sessionnotready'] = 'Session not ready';
$string['sessionrequiresorganiserinstructions'] = 'This session requires an organiser to be nominated. You can either nominate yourself as the organiser and start the session, or wait for someone else to do so. Please note that the organiser of a meeting cannot be changed once it has been defined.';
$string['teammeeting:addinstance'] = 'Add a Team meeting instance';
$string['teammeeting:presentmeeting'] = 'Whether the user can organise or present online meetings';
$string['teammeeting:view'] = 'View a Team meeting instance';
$string['usinggroupsbutnogroupsavailable'] = 'This activity is using groups, but none of the groups are available to you.';
$string['waitinstead'] = 'Wait instead';
$string['whatwouldyouliketodo'] = 'What would you like to do?';
$string['yourgroups'] = 'Your groups';
