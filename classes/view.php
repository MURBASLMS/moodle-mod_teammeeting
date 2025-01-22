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

use Exception;
use local_callista\form\request\uc;

/**
 * View.
 *
 * @package    mod_teammeeting
 * @copyright  2025 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view {
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

    /** @var array User's groups */
    protected $usergroups;
    /** @var array Other groups */
    protected $othergroups;

    /** @var \stdClass The meeting record */
    protected $meeting;
    /** @var bool Whether the requested group was attemped. */
    protected $requestedgroupwascalled = false;

    /**
     * Constructor
     *
     * @param int $cmid The course module ID
     */
    public function __construct($cmid) {
        global $DB;

        $this->cm = get_coursemodule_from_id('teammeeting', $cmid, 0, false, MUST_EXIST);
        $this->course = $DB->get_record('course', array('id' => $this->cm->course), '*', MUST_EXIST);
        $this->teammeeting = $DB->get_record('teammeeting', array('id' => $this->cm->instance), '*', MUST_EXIST);
        $this->context = \context_module::instance($this->cm->id);
    }

    /**
     * Check if user can manage.
     *
     * @return bool
     */
    public function can_manage(): bool {
        return has_capability('mod/teammeeting:addinstance', $this->context);
    }

    /**
     * Check if user can present in the current group
     *
     * @return bool
     */
    public function can_present_in_group() {
        $canpresent = has_capability('mod/teammeeting:presentmeeting', $this->context);
        $groupmode = groups_get_activity_groupmode($this->cm, $this->course);
        $aag = has_capability('moodle/site:accessallgroups', $this->context);
        return $canpresent && ($groupmode != SEPARATEGROUPS || $aag || array_key_exists($this->groupid, $this->get_user_groups()));
    }

    /**
     * Get the cm.
     *
     * @return object The cm object.
     */
    public function get_cm() {
        return $this->cm;
    }

    /**
     * Get the course.
     *
     * @return object The course object.
     */
    public function get_course() {
        return $this->course;
    }

    /**
     * Get the group ID.
     *
     * @return int The group ID.
     */
    public function get_group_id() {
        if ($this->groupid !== null) {
            return $this->groupid;
        }

        return $this->groupid;
    }

    /**
     * Get the meeting record.
     *
     * @return \stdClass
     */
    public function get_meeting() {
        if (!$this->requestedgroupwascalled) {
            throw new \coding_exception('You must call set_requested_group_id().');
        }

        if (!isset($this->meeting)) {
            $meeting = helper::get_meeting_record($this->teammeeting, $this->groupid);

            // Assign an organiser by default, if we can.
            if (empty($meeting->organiserid)) {
                $meeting->organiserid = helper::get_default_organiser($this->teammeeting, $this->groupid);
                if (!empty($meeting->organiserid)) {
                    helper::save_meeting_record($meeting);
                }
            }

            // Hmm... the meeting has not yet been created but we have an organiser. A possible reason
            // for this to is that the meeting creation failed after an organiser was nominated. Or when
            // the default organiser was just assigned. If there was an error, to expose it, we will
            // attempt to recreate the meeting here, but only if the user can manage or present the meeting.
            // Students should fallback in the lobby.
            if (!empty($meeting->organiserid) && empty($meeting->meetingurl)) {
                if ($this->can_manage() || $this->can_present_in_group()) {
                    $meeting = helper::create_onlinemeeting_instance($this->teammeeting, $this->groupid);
                }
            }

            $this->meeting = $meeting;
        }
        return $this->meeting;
    }

    /**
     * Get the meeting URL.
     *
     * @return string
     */
    public function get_meeting_url(): string {
        $url = $this->get_meeting()->meetingurl;
        if (empty($url)) {
            throw new \coding_exception('Cannot read meeting URL before it exists.');
        }
        return $url;
    }

    /**
     * Get the teammeeting resource.
     *
     * @return object The object from the teammeeting table.
     */
    public function get_resource() {
        return $this->teammeeting;
    }

    /**
     * Get the other groups.
     *
     * @return array
     */
    public function get_other_groups() {
        $this->init_groups();
        return $this->othergroups;
    }

    /**
     * Get the user groups.
     *
     * @return array
     */
    public function get_user_groups() {
        $this->init_groups();
        return $this->usergroups;
    }

    /**
     * Whether the user has no groups.
     *
     * @return bool
     */
    public function has_no_groups() {
        $this->init_groups();
        return empty($this->usergroups) && empty($this->othergroups);
    }

    /**
     * Get the user's groups.
     *
     * @return array
     */
    protected function init_groups() {
        global $USER;
        if (!isset($this->usergroups)) {
            $cm = $this->cm;
            $aag = has_capability('moodle/site:accessallgroups', $this->context);
            $groupmode = groups_get_activity_groupmode($cm, $this->course);

            $usergroups = [];
            $othergroups = [];

            if ($groupmode == VISIBLEGROUPS || $aag) {
                $allgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
                $usergroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
                $othergroups = array_diff_key($allgroups, $usergroups);
            } else if ($groupmode == SEPARATEGROUPS) {
                $usergroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
            }

            $this->usergroups = $usergroups;
            $this->othergroups = $othergroups;
        }

    }

    /**
     * Check if the meeting is available.
     *
     * @return bool
     */
    public function is_meeting_available() {
        if ($this->teammeeting->reusemeeting) {
            return true;
        }
        // If it's a once off online meeting, and we're not within the open dates, we should advise to come back at a later time.
        return !($this->teammeeting->opendate > time() || $this->teammeeting->closedate < time());
    }

    /**
     * Mark the module as viewed.
     *
     * @return void
     */
    public function mark_as_viewed() {
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
     * Whether the user must select a group.
     *
     * That is the case when we could not best resolve the group to pick for that user.
     *
     * @return bool
     */
    public function must_select_group() {
        if (!$this->requestedgroupwascalled) {
            throw new \coding_exception('You must call set_requested_group_id().');
        }
        return $this->groupid === null;
    }

    /**
     * Require that the user can view.
     *
     * @return bool
     */
    public function require_can_view() {
        return require_capability('mod/teammeeting:view', $this->context);
    }

    /**
     * Require that the user can view.
     *
     * @return void
     */
    public function require_login() {
        return require_course_login($this->course, true, $this->cm);
    }

    /**
     * Resolve the group.
     *
     * That should be used when no group IDs were requested.
     *
     * @return int|null Null when must be chosen, 0 when resolved to none, else an ID.
     */
    protected function resolve_group_id() {

        // The meeting is forced to a specific group.
        if ($this->teammeeting->groupid) {
            return $this->teammeeting->groupid;
        }

        $groupid = null;
        $cm = $this->cm;

        // Identify the meeting, via the group mode.
        $groupmode = groups_get_activity_groupmode($cm, $this->course);
        $usegroups = $groupmode != NOGROUPS;

        // If we do not use groups, or there is only one group to select from.
        if ($groupid === null) {
            $usergroups = $this->get_user_groups();
            if (!$usegroups || (count($usergroups) === 1 && empty($othergroups))) {
                $groupid = $usegroups ? reset($usergroups)->id : 0;
            }
        }

        return $groupid;
    }

    /**
     * Set the requested group ID.
     *
     * There is no guarantee that this group ID will be used. This method will attempt to
     * identify the group that should be used. When uncertain, this method will leave the
     * group ID as null.
     *
     * @param int|null $groupid
     */
    public function set_requested_group_id(?int $groupid) {
        global $USER;

        $this->requestedgroupwascalled = true;

        // Override when activity is set to specific group.
        $groupid = $this->teammeeting->groupid ? $this->teammeeting->groupid : $groupid;

        if ($groupid !== null && !helper::can_access_group($this->teammeeting, $USER->id, $groupid)) {
            if (!empty($this->teammeeting->groupid)) {
                // If we're requesting the activity group but we cannot access it.
                throw new \moodle_exception('cannotaccessgroup', 'mod_teammeeting');
            }
            $groupid = null;
        }

        if ($groupid === null) {
            $groupid = $this->resolve_group_id();
        }

        $this->groupid = $groupid;
    }

    /**
     * Whether we should display the lobby.
     *
     * Wait, the meeting does not have an organiser yet (or meeting), we display the lobby.
     *
     * @return bool
     */
    public function should_display_lobby() {
        $meeting = $this->get_meeting();
        return empty($meeting->organiserid) || empty($meeting->meetingurl);
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
     * Update the presenters list.
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
