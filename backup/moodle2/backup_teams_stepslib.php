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
 * Backup steps.
 *
 * @package    mod_teams
 * @copyright  2021 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup steps.
 *
 * @package    mod_teams
 * @copyright  2021 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_teams_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // Define each element separated.
        $teams = new backup_nested_element('teams', ['id'], [
            'name', 'intro', 'introformat', 'externalurl', 'opendate', 'closedate',
            'onlinemeetingid', 'creatorid', 'reusemeeting', 'timemodified'
        ]);

        // Define sources.
        $teams->set_source_table('teams', array('id' => backup::VAR_ACTIVITYID));

        // Define ID annotations.
        $teams->annotate_ids('user', 'creatorid');

        // Define file annotations.
        $teams->annotate_files('mod_teams', 'intro', null);

        return $this->prepare_activity_structure($teams);
    }

}
