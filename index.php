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
 * Index page.
 *
 * @package   mod_teammeeting
 * @copyright 2021 Université Clermont Auvergne
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_teammeeting\helper;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/teammeeting/lib.php');

// For this type of page this is the course id.
$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);

$PAGE->set_url('/mod/teammeeting/index.php', array('id' => $id));
$PAGE->set_pagelayout('incourse');

$event = \mod_teammeeting\event\course_module_instance_list_viewed::create(['context' => $context]);
$event->add_record_snapshot('course', $course);
$event->trigger();

// Print the header.
$strplural = get_string('modulenameplural', 'teammeeting');
$PAGE->navbar->add($strplural);
$PAGE->set_title($strplural);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($strplural));

require_capability('mod/teammeeting:view', $context);
$canmanage = has_capability('mod/teammeeting:addinstance', $context);

$allteams = get_all_instances_in_course('teammeeting', $course);
$teams = array_filter($allteams, function($team) use ($USER) {
    if (!has_capability('mod/teammeeting:view', context_module::instance($team->coursemodule))) {
        return false;
    } else if ($team->groupid && !helper::can_access_group($team, $USER->id, $team->groupid)) {
        return false;
    }
    return true;
});

if (!$teams) {
    notice(get_string('noinstancesofplugin', 'mod_teammeeting'), new moodle_url('/course/view.php', ['id' => $course->id]));
    die;
}

// Print the table.
$table = new html_table();
$table->head = [
    get_string('sectionname', 'format_' . $course->format),
    get_string('name', 'core'),
];
if ($canmanage) {
    $table->head[] = get_string('group', 'core');
    $table->head[] = get_string('active', 'mod_teammeeting');
    $table->head[] = get_string('meetingurl', 'mod_teammeeting');
};
$table->align = array_fill(0, count($table->head), 'left');

foreach ($teams as $team) {
    $link = html_writer::link(new moodle_url('/mod/teammeeting/view.php', ['id' => $team->coursemodule]),
            format_string($team->name), ['class' => !$team->visible ? 'dimmed' : '']);

    $groupid = $team->groupid;
    $group = '-';
    if (!empty($groupid)) {
        $group = groups_get_group_name($groupid);
    } else if ($team->groupmode == VISIBLEGROUPS) {
        $group = $OUTPUT->render(new pix_icon('i/groupv', get_string('groupsvisible', 'core'), 'core'));
    } else if ($team->groupmode == SEPARATEGROUPS) {
        $group = $OUTPUT->render(new pix_icon('i/groups', get_string('groupsseparate', 'core'), 'core'));
    }

    // Attempt to find the one meeting instance.
    $meeting = null;
    $meetingurl = null;
    if ($groupid || $team->groupmode == NOGROUPS) {
        $meeting = helper::get_meeting_record($team, $groupid);
        $meetingurl = $meeting->meetingurl;
    }

    // Determining whether the activity is "active", we do not know if we do no have a meeting.
    $isactive = null;
    if ($meeting !== null) {
        $isactive = !empty($meetingurl);
    }

    $activehtml = '-';
    if ($isactive === true) {
        $activehtml = html_writer::tag('span', get_string('yes', 'core'), ['class' => 'badge badge-success']);
    } else if ($isactive === false) {
        $activehtml = html_writer::tag('span', get_string('no', 'core'), ['class' => 'badge badge-warning']);
    }

    $meetingurlhtml = '-';
    if (!empty($meetingurl)) {
        $meetingurlhtml = html_writer::link(new moodle_url($meetingurl), get_string('link', 'mod_teammeeting'),
            ['target' => '_blank']);
    }

    $data = [
        get_section_name($course, $team->section),
        $link,
    ];
    if ($canmanage) {
        $data[] = $group;
        $data[] = $activehtml;
        $data[] = $meetingurlhtml;
    }
    $table->data[] = $data;
}

echo html_writer::table($table);
echo $OUTPUT->footer();
