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
 * Helper.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teammeeting;

use context_course;
use external_function_parameters;
use external_single_structure;
use external_value;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * External.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {

    /**
     * External function parameters.
     *
     * @return external_value
     */
    public static function is_meeting_ready_parameters() {
        return new external_function_parameters([
            'teammeetingid' => new external_value(PARAM_INT),
            'groupid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Is meeting ready?
     *
     * @return external_value
     */
    public static function is_meeting_ready($teammeetingid, $groupid) {
        global $DB, $USER;

        $params = static::validate_parameters(static::is_meeting_ready_parameters(), ['teammeetingid' => $teammeetingid,
            'groupid' => $groupid]);
        $teammeetingid = $params['teammeetingid'];
        $groupid = $params['groupid'];

        $teammeeting = $DB->get_record('teammeeting', ['id' => $teammeetingid], '*', MUST_EXIST);
        $context = context_course::instance($teammeeting->course);

        // Force the group ID, when set against the activity.
        if (!empty($teammeeting->groupid)) {
            $groupid = $teammeeting->groupid;
        }

        static::validate_context($context);
        require_capability('mod/teammeeting:view', $context);
        if (!helper::can_access_group($teammeeting, $USER->id, $groupid)) {
            throw new moodle_exception('cannotaccessgroup', 'mod_teammeeting');
        }

        $meeting = helper::get_meeting_record($teammeeting, $groupid);
        $isready = !empty($meeting->meetingurl);

        // If the meeting is a once off and the user cannot manage the activity, we present
        // that the meeting is not ready otherwise the user could retrieve the meeting URL early.
        if (!$teammeeting->reusemeeting) {
            $isclosed = $teammeeting->opendate > time() || $teammeeting->closedate < time();
            $canmanage = has_capability('mod/teammeeting:addinstance', $context);
            if (!$canmanage && $isclosed) {
                $isready = false;
            }
        }

        return [
            'isready' => $isready,
            'externalurl' => $isready ? $meeting->meetingurl : null,
        ];
    }

    /**
     * External function returns.
     *
     * @return external_value
     */
    public static function is_meeting_ready_returns() {
        return new external_single_structure([
            'isready' => new external_value(PARAM_BOOL),
            'externalurl' => new external_value(PARAM_URL, '', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * External function parameters.
     *
     * @return external_value
     */
    public static function nominate_organiser_parameters() {
        return new external_function_parameters([
            'teammeetingid' => new external_value(PARAM_INT),
            'groupid' => new external_value(PARAM_INT)
        ]);
    }

    /**
     * Nominate the organiser of a meeting.
     *
     * @return external_value
     */
    public static function nominate_organiser($teammeetingid, $groupid) {
        global $DB, $USER;

        $params = static::validate_parameters(static::nominate_organiser_parameters(), ['teammeetingid' => $teammeetingid,
            'groupid' => $groupid]);
        $teammeetingid = $params['teammeetingid'];
        $groupid = $params['groupid'];
        $organiserid = $USER->id; // Presently we always nominate ourselves, but this could be changed.
        $manager = manager::get_instance();
        $manager->require_is_available();

        $teammeeting = $DB->get_record('teammeeting', ['id' => $teammeetingid], '*', MUST_EXIST);
        $context = context_course::instance($teammeeting->course);
        static::validate_context($context);

        // Force the group ID, when set against the activity.
        if (!empty($teammeeting->groupid)) {
            $groupid = $teammeeting->groupid;
        }

        // The acting user must have the permission to present, or to manage the instance to assign the organiser.
        if (!has_any_capability(['mod/teammeeting:presentmeeting', 'mod/teammeeting:addinstance'], $context)) {
            require_capability('mod/teammeeting:presentmeeting', $context);
        }
        if (!helper::can_access_group($teammeeting, $USER->id, $groupid)) {
            throw new moodle_exception('cannotaccessgroup', 'mod_teammeeting');
        }

        $meeting = helper::get_meeting_record($teammeeting, $groupid);

        // Validate the state of the meeting.
        if (!empty($meeting->organiserid)) {
            throw new \moodle_exception('organiseralreadyset', 'mod_teammeeting');
        }

        // Validate that the user is not an organiser elsewhere.
        if ($DB->record_exists('teammeeting_meetings', ['teammeetingid' => $teammeetingid, 'organiserid' => $organiserid])) {
            throw new \moodle_exception('alreadyorganiserofothersession', 'mod_teammeeting');
        }

        // The organiser must have the permission to present the meeting.
        require_capability('mod/teammeeting:presentmeeting', $context, $organiserid);
        $manager->require_is_o365_user($organiserid);

        // Assign the organiser (and reset other fields).
        $meeting->organiserid = $organiserid;
        $meeting->onlinemeetingid = null;
        $meeting->meetingurl = null;
        $meeting->lastpresenterssync = 0;
        helper::save_meeting_record($meeting);

        // Now create the meeting.
        $meeting = helper::create_onlinemeeting_instance($teammeeting, $groupid);

        return [
            'externalurl' => $meeting->meetingurl,
        ];
    }

    /**
     * External function returns.
     *
     * @return external_value
     */
    public static function nominate_organiser_returns() {
        return new external_single_structure([
            'externalurl' => new external_value(PARAM_URL, '', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }
}
