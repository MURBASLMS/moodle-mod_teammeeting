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
 * Sync participants.
 *
 * @package    mod_teammeeting
 * @copyright  2023 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_teammeeting\helper;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$usage = "Sync the participants.

Usage:
    $ php get_meeting_info.php --cmid=<cmid>

Options:
    -h --help                   Print this help.
    --all                       Sync all meetings needing sync.

Examples:

    $ php sync_participants.php --all
        Synchronise the participants of all meetings that have not been synced very recently.
";

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'all' => false,
], [
    'h' => 'help'
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(1);
}

if ($options['all'] === false) {
    cli_problem('You must pass the parameter --all.');
    cli_writeln($usage);
    exit(2);
}

$cutoffts = time() - 5 * 60;
mtrace('Retrieving meetings last synced before ' . userdate($cutoffts));
$recordset = $DB->get_recordset_select('teammeeting_meetings', 'lastpresenterssync < ?', [$cutoffts], 'teammeetingid ASC');
$teammeeting = null;
$cmid = null;
foreach ($recordset as $meeting) {
    if (!$teammeeting || $teammeeting->id !== $meeting->teammeetingid) {
        $teammeeting = $DB->get_record('teammeeting', ['id' => $meeting->teammeetingid], '*', MUST_EXIST);
    }
    $cminfo = get_fast_modinfo($teammeeting->course)->instances['teammeeting'][$teammeeting->id];
    mtrace('Syncing meeting instance ' . $meeting->id . ' (cmid: ' . $cminfo->id . ', teammeetingid: ' . $teammeeting->id . '))');
    try {
        helper::update_teammeeting_instance_attendees($teammeeting, $meeting);
    } catch (moodle_exception $e) {
        mtrace(' An error occurred while updating the participants:');
        mtrace('  ' . $e->getMessage());
        continue;
    }
    $DB->set_field('teammeeting_meetings', 'lastpresenterssync', time(), ['id' => $meeting->id]);
}
$recordset->close();
