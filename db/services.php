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
 * External services.
 *
 * @package    mod_teammeeting
 * @copyright  2022 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'mod_teammeeting_is_meeting_ready' => [
        'classname' => 'mod_teammeeting\\external',
        'methodname' => 'is_meeting_ready',
        'description' => 'Returns whether a meeting is ready.',
        'type' => 'read',
        'ajax' => true
    ],
    'mod_teammeeting_nominate_organiser' => [
        'classname' => 'mod_teammeeting\\external',
        'methodname' => 'nominate_organiser',
        'description' => 'Nominate the meeting organiser.',
        'type' => 'write',
        'ajax' => true
    ],
];
