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
 * Upgrade lib.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Migrate teammeeting instances to meetings table.
 */
function mod_teammeeting_migrate_to_meetings_table() {
    global $DB;

    $teammeetings = $DB->get_recordset('teammeeting', []);
    foreach($teammeetings as $teammeeting) {

        // There is nothing to migrate when we do not have either of these.
        if (empty($teammeeting->onlinemeetingid) && empty($teammeeting->organiserid)) {
            continue;
        }

        // Records already exist, we might have been running this script more than once, or running it
        // after new instances have been created. We should leave a notice and skip the upgrade.
        if ($DB->record_exists('teammeeting_meetings', ['teammeetingid' => $teammeeting->id])) {
            debugging('Entry in teammeeting_meetings table already exists for ' . $teammeeting->id);
            continue;
        }

        $record = (object) [
            'teammeetingid' => $teammeeting->id,
            'groupid' => 0,
            'organiserid' => $teammeeting->organiserid,
            'onlinemeetingid' => $teammeeting->onlinemeetingid,
            'meetingurl' => $teammeeting->externalurl,
            'lastpresenterssync' => $teammeeting->lastpresenterssync,
        ];
        $DB->insert_record('teammeeting_meetings', $record);
    }

}