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

require_once($CFG->libdir . '/externallib.php');
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
        global $OUTPUT;

        $args = (object) $args;
        $view = new \mod_teammeeting\meeting_view($args->cmid);
        $data = $view->get_page_data();

        // Pre-format some of the texts for the mobile app.
        $data['teammeeting']->name = external_format_string($data['teammeeting']->name, $data['context']);
        list($data['teammeeting']->intro, $data['teammeeting']->introformat) = 
            external_format_text($data['teammeeting']->intro, $data['teammeeting']->introformat,
                $data['context'], 'mod_teammeeting', 'intro');

        $details = teammeeting_print_details_dates($data['teammeeting'], "text");
        $gotoresource = $view->is_meeting_available() || $data['canmanage'];

        if (!$gotoresource) {
            $details = get_string('meetingnotavailable', 'mod_teammeeting', 
                teammeeting_print_details_dates($data['teammeeting'], "text"));
        }

        $defaulturl = new \moodle_url('/course/view.php', array('id' => $data['course']->id));
        
        $templatedata = [
            'cmid' => $data['cm']->id,
            'meeting' => $data['teammeeting'],
            'details' => $details,
            'gotoresource' => $gotoresource,
            'defaulturl' => $defaulturl->out()
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
