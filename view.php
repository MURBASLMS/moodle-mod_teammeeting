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
 * View page.
 *
 * @package   mod_teammeeting
 * @copyright 2020 Université Clermont Auvergne
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_teammeeting\helper;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/teammeeting/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/tablelib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$u = optional_param('u', 0, PARAM_INT); // Team instance id.
$redirect = optional_param('redirect', 0, PARAM_BOOL);
$groupid = optional_param('groupid', null, PARAM_INT);

if ($u) { // Two ways to specify the module.
    $resource = $DB->get_record('teammeeting', array('id' => $u), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('teammeeting', $resource->id, $resource->course, false, MUST_EXIST);
} else {
    $cm = get_coursemodule_from_id('teammeeting', $id, 0, false, MUST_EXIST);
    $resource = $DB->get_record('teammeeting', array('id' => $cm->instance), '*', MUST_EXIST);
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/teammeeting:view', $context);

$pageparams = ['id' => $cm->id];
if ($groupid !== null) {
    $pageparams['groupid'] = $groupid;
}
$PAGE->set_url('/mod/teammeeting/view.php', $pageparams);
$PAGE->set_title($course->shortname . ': ' . $resource->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($resource);

$canmanage = has_capability('mod/teammeeting:addinstance', $context);
$canpresent = has_capability('mod/teammeeting:presentmeeting', $context);
$courseurl = new moodle_url('/course/view.php', array('id' => $cm->course));

// If it's a once off online meeting, and we're not within the open dates,
// advise students to come back at a later time.
if (!$resource->reusemeeting) {
    $isclosed = $resource->opendate > time() || $resource->closedate < time();
    if (!$canmanage && $isclosed) {
        notice(get_string('meetingnotavailable', 'mod_teammeeting', teammeeting_print_details_dates($resource, "text")),
            $courseurl);
        die();
    }
}

// Broadcast module viewed event.
$event = \mod_teammeeting\event\course_module_viewed::create([
    'context' => $context,
    'objectid' => $resource->id
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('teammeeting', $resource);
$event->trigger();

// Mark activity has having been viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// If the user cannot access the group provided, reset the group ID to let the default behaviour take place.
if ($groupid !== null && !helper::can_access_group($resource, $USER->id, $groupid)) {
    $groupid = null;
}

// Identify the meeting, via the group mode.
$aag = has_capability('moodle/site:accessallgroups', $context);
$groupmode = groups_get_activity_groupmode($cm, $course);

$usegroups = $groupmode != NOGROUPS;
$allgroups = [];
$usergroups = [];
$othergroups = [];

if ($groupmode == VISIBLEGROUPS || $aag) {
    $allgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
    $usergroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
    $othergroups = array_diff_key($allgroups, $usergroups);
} else if ($groupmode == SEPARATEGROUPS) {
    $usergroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
    $allgroups = $usergroups;
}

// If we do not use groups, or there is only one group to select from.
if ($groupid === null) {
    if (!$usegroups || (count($usergroups) === 1 && empty($othergroups))) {
        $groupid = $usegroups ? reset($usergroups)->id : 0;
    }
}

// The user should be choosing a group.
if ($groupid === null) {
    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($resource->name), 2);

    if (empty($usergroups) && empty($othergroups)) {
        $notification = new \core\output\notification(get_string('usinggroupsbutnogroupsavailable', 'mod_teammeeting'),
            \core\output\notification::NOTIFY_ERROR);
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
        echo $OUTPUT->footer();
        die();
    }

    if (!empty($resource->intro)) {
        echo $OUTPUT->box(format_module_intro('teammeeting', $resource, $cm->id), 'generalbox', 'intro');
    }

    $meetingsbygroupid = array_reduce(
        $DB->get_records('teammeeting_meetings', ['teammeetingid' => $resource->id]),
        function($carry, $record) {
            $carry[$record->groupid] = $record;
            return $carry;
        },
        []
    );

    echo teammeeting_print_details_dates($resource);

    echo html_writer::tag('p', get_string('selectgroupformeeting', 'mod_teammeeting'));

    $table = new flexible_table('teammeeting-groups');
    $table->define_baseurl($PAGE->url);
    $table->define_columns(['name', 'organiser']);
    $table->define_headers([get_string('group', 'core'), get_string('organiser', 'mod_teammeeting')]);
    $table->setup();
    $table->start_output();
    foreach ($usergroups as $group) {
        $url = new moodle_url($PAGE->url, ['groupid' => $group->id, 'redirect' => 1]);
        $meeting = isset($meetingsbygroupid[$group->id]) ? $meetingsbygroupid[$group->id] : null;
        $organiser = $meeting && !empty($meeting->organiserid) ? core_user::get_user($meeting->organiserid) : null;
        $organisername = $organiser ? fullname($organiser) : '';
        $table->add_data([html_writer::link($url, format_string($group->name)), $organisername]);
    }
    if (!empty($usergroups) && !empty($othergroups)) {
        $table->add_data([html_writer::tag('strong', get_string('othergroups', 'mod_teammeeting')), '']);
    }
    foreach ($othergroups as $group) {
        $url = new moodle_url($PAGE->url, ['groupid' => $group->id, 'redirect' => 1]);
        $meeting = isset($meetingsbygroupid[$group->id]) ? $meetingsbygroupid[$group->id] : null;
        $organiser = $meeting && !empty($meeting->organiserid) ? core_user::get_user($meeting->organiserid) : null;
        $organisername = $organiser ? fullname($organiser) : '';
        $table->add_data([html_writer::link($url, format_string($group->name)), $organisername]);
    }
    $table->finish_output();

    echo $OUTPUT->footer();
    return;
}

// Get the meeting record for this group.
$meeting = helper::get_meeting_record($resource, $groupid);
$meetingurl = $meeting->meetingurl;
$canpresentingroup = $canpresent && ($groupmode != SEPARATEGROUPS || array_key_exists($groupid, $usergroups));

// Hmm... the meeting has not yet been created but we have an organiser. A possible reason
// for this to is that the meeting creation failed after an organiser was assigned.
// In order to expose the error, we will attempt to recreate the meeting here, but only
// if the user can manage or present the meeting. Students should fallback in the lobby.
if (!empty($meeting->organiserid) && empty($meetingurl) && ($canmanage || $canpresentingroup)) {
    $meeting = helper::create_onlinemeeting_instance($resource, $groupid);
}

// Wait, the meeting does not have an organiser yet (or meeting), we display the lobby.
// From the lobby, users will be automatically redirected to the meeting
// without cycling back through this page.
if (empty($meeting->organiserid) || empty($meetingurl)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($resource->name), 2);
    if (!empty($resource->intro)) {
        echo $OUTPUT->box(format_module_intro('teammeeting', $resource, $cm->id), 'generalbox', 'intro');
    }
    echo teammeeting_print_details_dates($resource);

    echo $OUTPUT->render_from_template('mod_teammeeting/lobby', [
        'canpresent' => $canpresentingroup,
        'teammeetingid' => $resource->id,
        'groupid' => $groupid
    ]);

    echo $OUTPUT->footer();
    die();
}

// Update the list of presenters. We do not need to do this for meetings that have an
// end date, but that is already filtered above. In all other cases, it's best that we
// update this every 5 minutes to make sure we're in sync with the latest roles before
// the meeting is launched. Once the meeting is started (1 attendee joins), changes
// to the meeting role & permissions will not have effect until all attendees leave and
// join again.
$shouldupdatepresenters = !empty($meeting->id) && $meeting->lastpresenterssync < time() - 5 * 60;
if ($shouldupdatepresenters) {
    // We update the lastpresenterssync right away to limit the number of concurrent requests that sync.
    $origlastpresenterssync = $meeting->lastpresenterssync;
    $meeting->lastpresenterssync = time();
    $DB->set_field('teammeeting_meetings', 'lastpresenterssync', $meeting->lastpresenterssync, ['id' => $meeting->id]);
    try {
        helper::update_teammeeting_instance_attendees($resource, $meeting);
    } catch (Exception $e) {
        $DB->set_field('teammeeting', 'lastpresenterssync', $origlastpresenterssync, ['id' => $meeting->id]);
        throw $e;
    }
}

// If a redirect is request.
if ($redirect) {

    // When the course does not have a view page, we should not redirect teachers right-away,
    // or they could be stuck not being able to edit the page. We always show them the intermediate page.
    $hascoursepage = course_get_format($course)->has_view_page();
    if ($hascoursepage || !$canmanage) {
        redirect($meetingurl);
    }
}

// Display the page.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($resource->name), 2);

if (!empty($resource->intro)) {
    echo $OUTPUT->box(format_module_intro('teammeeting', $resource, $cm->id), 'generalbox', 'intro');
}

echo teammeeting_print_details_dates($resource);

echo $OUTPUT->render_from_template('mod_teammeeting/view', [
    'meetingurl' => $meetingurl
]);

echo $OUTPUT->footer();
