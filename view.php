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

$id = required_param('id', PARAM_INT); // Course Module ID.
$u = optional_param('u', 0, PARAM_INT); // Team instance id.
$redirect = optional_param('redirect', 0, PARAM_BOOL);

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

$PAGE->set_url('/mod/teammeeting/view.php', array('id' => $cm->id));
$PAGE->set_title($course->shortname . ': ' . $resource->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($resource);

$canmanage = has_capability('mod/teammeeting:addinstance', $context);
$courseurl = new moodle_url('/course/view.php', array('id' => $cm->course));
$meetingurl = $resource->externalurl;

// Once off online meeting.
if (!$resource->reusemeeting) {
    $isclosed = $resource->opendate > time() || $resource->closedate < time();
    if (!$canmanage && $isclosed) {
        notice(get_string('meetingnotavailable', 'mod_teammeeting', teammeeting_print_details_dates($resource, "text")),
            $courseurl);
        die();
    }
}

$event = \mod_teammeeting\event\course_module_viewed::create([
    'context' => $context,
    'objectid' => $resource->id
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('teammeeting', $resource);
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
    echo $OUTPUT->box(format_module_intro('teammeeting', $resource, $cm->id), 'generalbox', 'intro');
}

echo teammeeting_print_details_dates($resource);

echo $OUTPUT->render_from_template('mod_teammeeting/view', [
    'meetingurl' => $meetingurl
]);

echo $OUTPUT->footer();
