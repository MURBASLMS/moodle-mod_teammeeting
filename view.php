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
use mod_teammeeting\view;

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
    $cmid = get_coursemodule_from_instance('teammeeting', $resource->id, $resource->course, false, MUST_EXIST)->id;
} else {
    $cmid = $id;
}

$view = new view($cmid, $groupid);
$view->require_login();
$view->require_can_view();

$cm = $view->get_cm();
$course = $view->get_course();
$resource = $view->get_resource();

$pageparams = ['id' => $cm->id];
if ($groupid !== null) {
    $pageparams['groupid'] = $groupid;
}
$PAGE->set_url('/mod/teammeeting/view.php', $pageparams);
$PAGE->set_title($course->shortname . ': ' . $resource->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($resource);
$PAGE->add_body_class('limitedwidth');

$courseurl = new moodle_url('/course/view.php', array('id' => $cm->course));

// If it's a once off online meeting, and we're not within the open dates,
// advise students to come back at a later time.
if (!$view->is_meeting_available()) {
    notice(get_string('meetingnotavailable', 'mod_teammeeting', teammeeting_print_details_dates($resource, "text")), $courseurl);
    die();
}

$view->set_requested_group_id($groupid);
$view->mark_as_viewed();

// The user should be choosing a group.
if ($view->must_select_group()) {
    echo $OUTPUT->header();

    if ($view->has_no_groups()) {
        $notification = new \core\output\notification(get_string('usinggroupsbutnogroupsavailable', 'mod_teammeeting'),
            \core\output\notification::NOTIFY_ERROR);
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
        echo $OUTPUT->footer();
        die();
    }

    $usergroups = $view->get_user_groups();
    $othergroups = $view->get_other_groups();
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

// Wait, the meeting does not have an organiser yet (or meeting), we display the lobby.
// From the lobby, users will be automatically redirected to the meeting without cycling back through this page.
if ($view->should_display_lobby()) {
    echo $OUTPUT->header();

    echo teammeeting_print_details_dates($resource);

    echo $OUTPUT->render_from_template('mod_teammeeting/lobby', [
        'canpresent' => $view->can_present_in_group(),
        'teammeetingid' => $resource->id,
        'groupid' => $view->get_group_id()
    ]);

    echo $OUTPUT->footer();
    die();
}

// Update the presenters, It's best that we update this every 5 minutes to make sure we're in sync with the latest
// roles before the meeting is launched. Once the meeting is started (1 attendee joins), changes to the meeting
// role & permissions will not have effect until all attendees leave and join again.
if ($view->should_update_presenters()) {
    $view->update_presenters();
}

// If a redirect is request.
if ($redirect) {

    // When the course does not have a view page, we should not redirect teachers right-away,
    // or they could be stuck not being able to edit the page. We always show them the intermediate page.
    $hascoursepage = course_get_format($course)->has_view_page();
    if ($hascoursepage || !$view->can_manage()) {
        redirect($view->get_meeting_url());
    }
}

// Display the page.
echo $OUTPUT->header();

echo teammeeting_print_details_dates($view->get_resource());

echo $OUTPUT->render_from_template('mod_teammeeting/view', [
    'meetingurl' => $view->get_meeting_url()
]);

echo $OUTPUT->footer();
