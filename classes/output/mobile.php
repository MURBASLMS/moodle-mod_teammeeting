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
 * Provides {@see \mod_teams\output\mobile} class.
 *
 * @copyright  2021 Anthony Durif
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teams\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teams/lib.php');

/**
 * Controls the display of the plugin in the Mobile App.
 *
 * @package    mod_teams
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
        $cm = get_coursemodule_from_id('teams', $args->cmid);
        $context = \context_module::instance($cm->id);

        require_login($args->courseid, false, $cm, true, true);
        require_capability('mod/teams:view', $context);

        $teams = $DB->get_record('teams', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = get_course($cm->course);
        $canmanage = has_capability('mod/teams:addinstance', $context);

        // Pre-format some of the texts for the mobile app.
        $teams->name = external_format_string($teams->name, $context);
        list($teams->intro, $teams->introformat) = external_format_text($teams->intro, $teams->introformat,
            $context, 'mod_teams', 'intro');

        $details = teams_print_details_dates($teams, "text");
        $gotoresource = true;

        // Once off online meeting.
        if (!$teams->reuse_meeting) {
            $isclosed = $teams->opendate > time() || $teams->closedate < time();
            if (!$canmanage && $isclosed) {
                $details = get_string('meetingnotavailable', 'mod_teams', teams_print_details_dates($teams, "text"));
                $gotoresource = false;
            }
        }


        $defaulturl = new \moodle_url('/course/view.php', array('id' => $course->id));
        $defaulturl = $defaulturl->out();

        $data = [
            'cmid' => $cm->id,
            'teams' => $teams,
            'details' => $details,
            'gotoresource' => $gotoresource,
            'defaulturl' => $defaulturl
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_teams/mobile_view', $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => '',
            'files' => [],
        ];
    }
}