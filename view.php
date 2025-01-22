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

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/teammeeting/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/tablelib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$u = optional_param('u', 0, PARAM_INT); // Team instance id.
$redirect = optional_param('redirect', 0, PARAM_BOOL);
$groupid = optional_param('groupid', null, PARAM_INT);

if ($u) {
    $cmid = get_coursemodule_from_instance('teammeeting', $u, 0, false, MUST_EXIST)->id;
} else {
    $cmid = $id;
}

$view = new \mod_teammeeting\meeting_view($cmid, $groupid);
$data = $view->get_page_data();

// Set up page.
$PAGE->set_url('/mod/teammeeting/view.php', ['id' => $data['cm']->id]);
if ($groupid !== null) {
    $PAGE->url->param('groupid', $groupid);
}
$PAGE->set_title($data['course']->shortname . ': ' . $data['teammeeting']->name);
$PAGE->set_heading($data['course']->fullname);
$PAGE->set_activity_record($data['teammeeting']);
$PAGE->add_body_class('limitedwidth');

$courseurl = new moodle_url('/course/view.php', array('id' => $data['cm']->course));

// Check availability.
if (!$view->is_meeting_available() && !$data['canmanage']) {
    notice(get_string('meetingnotavailable', 'mod_teammeeting', 
        teammeeting_print_details_dates($data['teammeeting'], "text")),
        $courseurl);
    die();
}

// The user should be choosing a group.
if ($data['groupid'] === null) {
    echo $OUTPUT->header();

    if (empty($data['usergroups']) && empty($data['othergroups'])) {
        $notification = new \core\output\notification(
            get_string('usinggroupsbutnogroupsavailable', 'mod_teammeeting'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
        echo $OUTPUT->footer();
        die();
    }

    $meetingsbygroupid = array_reduce(
        $DB->get_records('teammeeting_meetings', ['teammeetingid' => $data['teammeeting']->id]),
        function($carry, $record) {
            $carry[$record->groupid] = $record;
            return $carry;
        },
        []
    );

    echo teammeeting_print_details_dates($data['teammeeting']);
    echo html_writer::tag('p', get_string('selectgroupformeeting', 'mod_teammeeting'));

    $table = new flexible_table('teammeeting-groups');
    $table->define_baseurl($PAGE->url);
    $table->define_columns(['name', 'organiser']);
    $table->define_headers([get_string('group', 'core'), get_string('organiser', 'mod_teammeeting')]);
    $table->setup();
    $table->start_output();

    foreach ($data['usergroups'] as $group) {
        $url = new moodle_url($PAGE->url, ['groupid' => $group->id, 'redirect' => 1]);
        $meeting = isset($meetingsbygroupid[$group->id]) ? $meetingsbygroupid[$group->id] : null;
        $organiser = $meeting && !empty($meeting->organiserid) ? core_user::get_user($meeting->organiserid) : null;
        $organisername = $organiser ? fullname($organiser) : '';
        $table->add_data([html_writer::link($url, format_string($group->name)), $organisername]);
    }

    if (!empty($data['usergroups']) && !empty($data['othergroups'])) {
        $table->add_data([html_writer::tag('strong', get_string('othergroups', 'mod_teammeeting')), '']);
    }

    foreach ($data['othergroups'] as $group) {
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

$meeting = $view->get_meeting();

// Update presenters if needed.
if ($view->should_update_presenters()) {
    $view->update_presenters();
}

// Show lobby if meeting not ready.
if (empty($meeting->organiserid) || empty($meeting->meetingurl)) {
    echo $OUTPUT->header();
    echo teammeeting_print_details_dates($data['teammeeting']);
    echo $OUTPUT->render_from_template('mod_teammeeting/lobby', [
        'canpresent' => $view->can_present_in_group(),
        'teammeetingid' => $data['teammeeting']->id,
        'groupid' => $data['groupid']
    ]);
    echo $OUTPUT->footer();
    die();
}

// Handle redirect.
if ($redirect) {
    $hascoursepage = course_get_format($data['course'])->has_view_page();
    if ($hascoursepage || !$data['canmanage']) {
        redirect($meeting->meetingurl);
    }
}

// Display the page.
echo $OUTPUT->header();
echo teammeeting_print_details_dates($data['teammeeting']);
echo $OUTPUT->render_from_template('mod_teammeeting/view', [
    'meetingurl' => $meeting->meetingurl
]);
echo $OUTPUT->footer();
