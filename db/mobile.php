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
 * Mobile.
 *
 * @package     mod_teammeeting
 * @copyright   2021 Anthony Durif, Université Clermont Auvergne
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_teammeeting' => [
        'handlers' => [
            'courseteams' => [
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/teammeeting/pix/icon.png',
                    'class' => '',
                ],

                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
            ],
        ],
        'lang' => [
            ['pluginname', 'mod_teammeeting'],
            ['gotoresource', 'mod_teammeeting'],
            ['returntocourse', 'mod_teammeeting'],
            ['sessionnotreadytryagainlater', 'mod_teammeeting'],
            ['viewontheweb', 'mod_teammeeting'],
        ],
    ],
];
