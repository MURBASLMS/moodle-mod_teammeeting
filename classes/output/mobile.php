<?php
// This file is part of Moodle - https://moodle.org/
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
 * Provides {@see \mod_teammeeting\output\mobile} class.
 *
 * @copyright  2021 Anthony Durif
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teammeeting\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teammeeting/lib.php');

/**
 * Controls the display of the plugin in the Mobile App.
 *
 * @package    mod_teammeeting
 * @category   output
 * @copyright  2021 Anthony Durif
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Return the data for the CoreCourseModuleDelegate delegate.
     *
     * @param object $args
     * @return object
     */
    public static function mobile_course_view($args) {
        global $OUTPUT, $DB;

        $args = (object) $args;
        $cm = get_coursemodule_from_id('teammeeting', $args->cmid);
        $context = \context_module::instance($cm->id);

        require_login($args->courseid, false, $cm, true, true);
        require_capability('mod/teammeeting:view', $context);

        $meeting = $DB->get_record('teammeeting', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = get_course($cm->course);
        $canmanage = has_capability('mod/teammeeting:addinstance', $context);

        // Pre-format some of the texts for the mobile app.
        $meeting->name = external_format_string($meeting->name, $context);
        list($meeting->intro, $meeting->introformat) = external_format_text($meeting->intro, $meeting->introformat,
            $context, 'mod_teammeeting', 'intro');

        $details = teammeeting_print_details_dates($meeting, "text");
        $gotoresource = true;

        // Once off online meeting.
        if (!$meeting->reusemeeting) {
            $isclosed = $meeting->opendate > time() || $meeting->closedate < time();
            if (!$canmanage && $isclosed) {
                $details = get_string('meetingnotavailable', 'mod_teammeeting', teammeeting_print_details_dates($meeting, "text"));
                $gotoresource = false;
            }
        }

        $defaulturl = new \moodle_url('/course/view.php', array('id' => $course->id));
        $defaulturl = $defaulturl->out();

        $data = [
            'cmid' => $cm->id,
            'meeting' => $meeting,
            'details' => $details,
            'gotoresource' => $gotoresource,
            'defaulturl' => $defaulturl
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_teammeeting/mobile_view', $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => '',
            'files' => [],
        ];
    }
}
