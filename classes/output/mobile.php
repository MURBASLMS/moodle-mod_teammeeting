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
 * @package    mod_teammeeting
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
        $args = (object) $args;
        $requestedgroupid = $args->groupid ?? null;

        $view = new \mod_teammeeting\view($args->cmid);
        $view->require_login();
        $view->require_can_view();

        $cm = $view->get_cm();
        $resource = $view->get_resource();
        $details = teammeeting_print_details_dates($resource, "text");
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $view->get_course()->id]));
        $cmurl = (new \moodle_url('/mod/teammeeting/view.php', ['id' => $cm->id]));

        $tplcontext = [
            'cmid' => $cm->id,
            'resource' => $resource,
            'details' => $details,
            'gotoresource' => true,
            'courseurl' => $courseurl->out(),
            'cmurl' => $cmurl->out(),
        ];

        if (!$view->is_meeting_available()) {
            return static::make_simple_response('unavailable', $tplcontext);
        }

        $view->set_requested_group_id($requestedgroupid);
        $view->mark_as_viewed();

        if ($view->must_select_group()) {
            return static::make_simple_response('select-group', $tplcontext);
        }

        if ($view->should_display_lobby()) {
            return static::make_simple_response('lobby', $tplcontext);
        }

        if ($view->should_update_presenters()) {
            $view->update_presenters();
        }

        return static::make_simple_response('view', $tplcontext + ['meeting' => $view->get_meeting()]);
    }

    /**
     * Make a simple response.
     *
     * @param string $template The template.
     * @param object $context The context.
     */
    protected static function make_simple_response($template, $context) {
        global $OUTPUT;
        return [
            'templates' => [
                [
                    'id' => $template,
                    'html' => $OUTPUT->render_from_template('mod_teammeeting/mobile/' . $template, $context),
                ],
            ],
            'javascript' => '',
            'otherdata' => '',
            'files' => [],
        ];
    }
}
