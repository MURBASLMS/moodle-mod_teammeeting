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
 * Teams module main user interface.
 *
 * @package    mod_teams
 * @copyright  2020 Université Clermont Auvergne
 */

use mod_teams\manager;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/teams/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$u = optional_param('u', 0, PARAM_INT); // Team instance id.
$redirect = optional_param('redirect', 0, PARAM_BOOL);

if ($u) { // Two ways to specify the module.
    $resource = $DB->get_record('teams', array('id' => $u), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('teams', $resource->id, $resource->course, false, MUST_EXIST);
} else {
    $cm = get_coursemodule_from_id('teams', $id, 0, false, MUST_EXIST);
    $resource = $DB->get_record('teams', array('id' => $cm->instance), '*', MUST_EXIST);
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/teams:view', $context);

$PAGE->set_url('/mod/teams/view.php', array('id' => $cm->id));
$PAGE->set_title($course->shortname . ': ' . $resource->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($resource);

$canmanage = has_capability('mod/teams:addinstance', $context);
$courseurl = new moodle_url('/course/view.php', array('id' => $cm->course));
$meetingurl = $resource->externalurl;

// Once off online meeting.
if (!$resource->reusemeeting) {
    $isclosed = $resource->opendate > time() || $resource->closedate < time();
    if (!$canmanage && $isclosed) {
        notice(get_string('meetingnotavailable', 'mod_teams', teams_print_details_dates($resource, "text")), $courseurl);
        die();
    }
}

$event = \mod_teams\event\course_module_viewed::create([
    'context' => $context,
    'objectid' => $resource->id
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('teams', $resource);
$event->trigger();

// Update 'viewed' state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

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
    echo $OUTPUT->box(format_module_intro('teams', $resource, $cm->id), 'generalbox', 'intro');
}

echo teams_print_details_dates($resource);

echo $OUTPUT->render_from_template('mod_teams/view', [
    'meetingurl' => $meetingurl
]);

echo $OUTPUT->footer();