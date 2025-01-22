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

namespace mod_teammeeting;

defined('MOODLE_INTERNAL') || die();

/**
 * Meeting view helper class
 *
 * @package    mod_teammeeting
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meeting_view {
    /** @var \stdClass The course module */
    protected $cm;
    /** @var \stdClass The course */
    protected $course;
    /** @var \stdClass The teammeeting instance */
    protected $teammeeting;
    /** @var \context_module The module context */
    protected $context;
    /** @var int The group ID */
    protected $groupid;
    /** @var bool Whether the user can manage */
    protected $canmanage;
    /** @var bool Whether the user can present */
    protected $canpresent;
    /** @var array User's groups */
    protected $usergroups;
    /** @var array All available groups */
    protected $allgroups;
    /** @var array Other groups */
    protected $othergroups;
    /** @var \stdClass The meeting record */
    protected $meeting;

    /**
     * Constructor
     *
     * @param int $cmid The course module ID
     * @param int|null $groupid Optional group ID
     */
    public function __construct($cmid, $groupid = null) {
        global $DB, $USER;

        $this->cm = get_coursemodule_from_id('teammeeting', $cmid, 0, false, MUST_EXIST);
        $this->course = $DB->get_record('course', array('id' => $this->cm->course), '*', MUST_EXIST);
        $this->teammeeting = $DB->get_record('teammeeting', array('id' => $this->cm->instance), '*', MUST_EXIST);
        $this->context = \context_module::instance($this->cm->id);
        $this->groupid = $groupid;

        require_course_login($this->course, true, $this->cm);
        require_capability('mod/teammeeting:view', $this->context);

        $this->canmanage = has_capability('mod/teammeeting:addinstance', $this->context);
        $this->canpresent = has_capability('mod/teammeeting:presentmeeting', $this->context);

        // Initialize groups.
        $this->init_groups();

        // Record the view.
        $this->record_view();
    }

    /**
     * Initialize groups information
     */
    protected function init_groups() {
        global $USER;

        $groupmode = groups_get_activity_groupmode($this->cm, $this->course);
        $aag = has_capability('moodle/site:accessallgroups', $this->context);

        $this->allgroups = [];
        $this->usergroups = [];
        $this->othergroups = [];

        if ($groupmode == VISIBLEGROUPS || $aag) {
            $this->allgroups = groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid);
            $this->usergroups = groups_get_all_groups($this->cm->course, $USER->id, $this->cm->groupingid);
            $this->othergroups = array_diff_key($this->allgroups, $this->usergroups);
        } else if ($groupmode == SEPARATEGROUPS) {
            $this->usergroups = groups_get_all_groups($this->cm->course, $USER->id, $this->cm->groupingid);
            $this->allgroups = $this->usergroups;
        }

        // Auto-select group if needed
        if ($this->groupid === null) {
            if ($groupmode == NOGROUPS || (count($this->usergroups) === 1 && empty($this->othergroups))) {
                $this->groupid = $groupmode == NOGROUPS ? 0 : reset($this->usergroups)->id;
            }
        }
    }

    /**
     * Record that this activity was viewed
     */
    protected function record_view() {
        $event = \mod_teammeeting\event\course_module_viewed::create([
            'context' => $this->context,
            'objectid' => $this->teammeeting->id
        ]);
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot('teammeeting', $this->teammeeting);
        $event->trigger();

        // Mark as completed.
        $completion = new \completion_info($this->course);
        $completion->set_module_viewed($this->cm);
    }

    /**
     * Check if the meeting is available
     *
     * @return bool
     */
    public function is_meeting_available() {
        if ($this->teammeeting->reusemeeting) {
            return true;
        }
        return !($this->teammeeting->opendate > time() || $this->teammeeting->closedate < time());
    }

    /**
     * Get the meeting record
     *
     * @return \stdClass
     */
    public function get_meeting() {
        if (!isset($this->meeting)) {
            $this->meeting = helper::get_meeting_record($this->teammeeting, $this->groupid);
            
            // Auto-assign organiser if needed.
            if (empty($this->meeting->organiserid)) {
                $this->meeting->organiserid = helper::get_default_organiser($this->teammeeting, $this->groupid);
                if (!empty($this->meeting->organiserid)) {
                    helper::save_meeting_record($this->meeting);
                }
            }

            // Try to create meeting if needed.
            if (!empty($this->meeting->organiserid) && empty($this->meeting->meetingurl)) {
                $canpresentingroup = $this->can_present_in_group();
                if ($this->canmanage || $canpresentingroup) {
                    $this->meeting = helper::create_onlinemeeting_instance($this->teammeeting, $this->groupid);
                }
            }
        }
        return $this->meeting;
    }

    /**
     * Check if user can present in the current group
     *
     * @return bool
     */
    public function can_present_in_group() {
        $groupmode = groups_get_activity_groupmode($this->cm, $this->course);
        $aag = has_capability('moodle/site:accessallgroups', $this->context);
        return $this->canpresent && ($groupmode != SEPARATEGROUPS || $aag || array_key_exists($this->groupid, $this->usergroups));
    }

    /**
     * Get all required data for display
     *
     * @return array
     */
    public function get_page_data() {
        return [
            'cm' => $this->cm,
            'course' => $this->course,
            'teammeeting' => $this->teammeeting,
            'context' => $this->context,
            'groupid' => $this->groupid,
            'canmanage' => $this->canmanage,
            'canpresent' => $this->canpresent,
            'usergroups' => $this->usergroups,
            'allgroups' => $this->allgroups,
            'othergroups' => $this->othergroups,
            'meeting' => $this->get_meeting()
        ];
    }

    /**
     * Should the presenters list be updated?
     *
     * @return bool
     */
    public function should_update_presenters() {
        $meeting = $this->get_meeting();
        return !empty($meeting->id) && $meeting->lastpresenterssync < time() - 5 * 60;
    }

    /**
     * Update the presenters list
     */
    public function update_presenters() {
        global $DB;
        
        $meeting = $this->get_meeting();
        $origlastpresenterssync = $meeting->lastpresenterssync;
        $meeting->lastpresenterssync = time();
        $DB->set_field('teammeeting_meetings', 'lastpresenterssync', $meeting->lastpresenterssync, ['id' => $meeting->id]);
        
        try {
            helper::update_teammeeting_instance_attendees($this->teammeeting, $meeting);
        } catch (Exception $e) {
            $DB->set_field('teammeeting', 'lastpresenterssync', $origlastpresenterssync, ['id' => $meeting->id]);
            throw $e;
        }
    }
}
