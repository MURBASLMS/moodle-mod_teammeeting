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
 * Backup task.
 *
 * @package    mod_teams
 * @copyright  2021 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/backup_teams_stepslib.php');

/**
 * Backup task.
 *
 * @package    mod_teams
 * @copyright  2021 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_teams_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the url.xml file
     */
    protected function define_my_steps() {
        $this->add_step(new backup_teams_activity_structure_step('teams_structure', 'teams.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts
     *
     * @param string $content Some HTML text.
     * @return string The content.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot . '/mod/teams', '#');

        // Access a list of all links in a course.
        $pattern = '#('.$base.'/index\.php\?id=)([0-9]+)#';
        $replacement = '$@TEAMSINDEX*$2@$';
        $content = preg_replace($pattern, $replacement, $content);

        // Access the link supplying a course module ID.
        $pattern = '#('.$base.'/view\.php\?id=)([0-9]+)#';
        $replacement = '$@TEAMSVIEWBYID*$2@$';
        $content = preg_replace($pattern, $replacement, $content);

        // Access the link supplying an instance ID.
        $pattern = '#('.$base.'/view\.php\?u=)([0-9]+)#';
        $replacement = '$@TEAMSVIEWBYU*$2@$';
        $content = preg_replace($pattern, $replacement, $content);

        return $content;
    }
}
