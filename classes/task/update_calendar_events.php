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
 * Task.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teammeeting\task;

use mod_teammeeting\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Task.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_calendar_events extends \core\task\adhoc_task {

    /**
     * Execute!
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $teammeetingid = $data->teammeetingid;

        // Get the meeting, or bail if it was deleted in the meantime.
        $teammeeting = $DB->get_record('teammeeting', ['id' => $teammeetingid]);
        if (!$teammeeting) {
            return;
        }

        helper::update_teammeeting_calendar_events($teammeeting);
    }

}
