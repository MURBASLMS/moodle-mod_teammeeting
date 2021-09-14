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
 * Strings for component 'teams', language 'en'
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
$string['notunique'] = 'The email is not unique in Azure Active Directory.';
$string['notfound'] = 'Email not found in Azure Active Directory.';

$string['name'] = 'Resource name';
$string['name_help'] = 'Resource name which will be displayed on your course page. A prefix will be added to identify the type of the ressource.';
$string['desc'] = '<div class="alert alert-info">Team creation is an action which can take few seconds.<br/>
                            It is also possible you do not have a direct access to this team, the access update can take few minutes. Thanks for your understanding.</div>';
$string['noaccount'] = '<p>It seems you do not have any microsoft account, so you cannot create a Team for now.</p>
                               <p>With your account created come back here to create your team from moodle.</p>';
$string['teamserror'] = '<p>It seems an error occured during your team creation: "{$a}".</p>
                                <p>Please retry this operation and contact the support if this problem persists.</p>';
$string['back'] = 'Return to course';
$string['type'] = 'Teams resource type to create';
$string['type_help'] = 'Teams resource type to create:
                            <ul><li>Team: create a team you will manage (add channels...) with members you selected in the course. </li>
                            <li>Online meeting (virtual classroom): create immediatly a meeting (or virtual classroom) users will be able to join by clicking on the resource link.</li></ul>';
$string['team'] = 'Team<br/>';
$string['meeting'] = 'Online meeting<br/>Virtual classroom';
$string['population'] = 'Population ';
$string['population_help'] = '<p>Select the populaton you want to enrol to the team:
                                <ul><li>enrolled users: all enrolled users will be added as team members and all course managers will be added as this team owner</li>
                                <li>enrolled students : only students enrolled to this course will be added as team members. By default only you will be will added as team owner and other course managers/teachers won\'t be.</li>
                                <li>one group or more: only this(these) group(s) members will be added as team members and you will be added as this team owner</li>
                                <li>chosen users: only users you selected will be added as team members and you will be added as this team owner</li></ul></p>';
$string['population_all'] = 'Enrolled users (students + teachers)';
$string['population_students'] = 'Enrolled students';
$string['population_groups'] = 'One group or more';
$string['population_users'] = 'Chosen users';
$string['enrol_managers'] = 'Enrol all course managers as team owners';
$string['enrol_managers_help'] = 'If enable all users with the "manager" role will be add to the team as owner and will have administration rights on it.<br/>
                                           If don\'t, you, as creator of the team, will be the only owner of this team.';
$string['opendate'] = 'Start date of the meeting';
$string['opendate_help'] = 'The meeting will be set to start at this particular point in time.';
$string['opendate_session'] = ' (Teams session start)';
$string['closedate'] = 'Closing date of the meeting';
$string['closedate_help'] = 'If left blank, the default duration of the meeting will apply.';
$string['closedate_session'] = ' (Teams session end)';
$string['dates_help'] = '<div class="alert alert-info"><strong>Be careful, students and other course users will not receive email notifications for participate tothis meeting.</strong>
                                    <ul><li>One shot meeting: <ul><li>The meeting will only be displayed on its creator Teams calendar. Students see this meeting on the course section where it has been added, on the "Upcoming events" course block and the Moodle calender (think to add theses blocks if needed).</li>
                                    <li>If you do not select a start date or an end date, a default period will be used (since the hour of the meeting creation and using a default duration value set in moodle administration). Tests will be made on moodle when you click on the activity link to redirect you or not to the Teams meeting in function of these period.</li></ul></li>
                                    <li>Permanent meeting: The meeting is only available and visible on the course section where it has been added and is usable since its creation.</li></ul>
                                    <p>Important: All changes about teams and meetings directly made in Teams (update of the meeting name, dates...) will not be reflected on Moodle.</p>';
$string['error_groups'] = 'Please select at least one group or change the population field value.';
$string['error_users'] = 'Please select at least one enrolled user or change the population field value.';
$string['error_dates'] = 'Closing date must be later than start date.';
$string['error_dates_past'] = 'The period you defined cannot correpond to an old period.';
$string['no_owner'] = '<p>You are not a owner of this team so that\'s why you cannot edit its properties.</p>
                                <p>Please contact a team owner to give you these administation rights.</p>';
$string['teamnotfound'] = 'Access to this team is not possible. A problem may be running on Microsoft Server, in this cas retry to connect later. It is also possible an owner delete this team.';
$string['meetingnotfound'] = 'Access to this team seems possible. A problem may be running on Microsoft Server, in this cas retry to connect later. It is also possible an organizer delete this meeting.';
$string['meetingnotavailable'] = 'Access to this meeting is not available. {$a} In case of difficulties please contact your course manager(s).';
$string['meetingavailablebetween'] = 'The online meeting is available between {$a->from} and {$a->to}.';
$string['description'] = 'Team created for the course "%s".';
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

$string['owners'] = 'Define this team owners';
$string['owners_help'] = '<p>Define users to add as team owners :
                            <ul><li>team creator only : only you or the user for who you create the team will be added as team owner. </li>
                            <li>team creator + selected users : only the creator (you or the user for you create the team) and users you select in the enrolled users list will be added ad team owners.</li>
                            <li>course managers : all this course managers will be added as team owners.</li></ul></p>';
$string['owners_creator'] = 'Team creator only';
$string['owners_others'] = 'Team creator + selected users';
$string['owners_managers'] = 'This course managers';
$string['other_owners'] = 'Select some other users as this team owner';
$string['other_owners_help'] = 'Select here the other user you want to give owner and adminisrations rights on this team.';
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