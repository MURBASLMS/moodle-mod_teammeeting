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
 * Upgrade.
 *
 * @package    mod_teammeeting
 * @copyright  2021 Université Clermont Auvergne
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teammeeting/db/upgradelib.php');

/**
 * Upgrade function.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool The result.
 */
function xmldb_teammeeting_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2022011003) {

        // Define field lastpresenterssync to be added to teammeeting.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('lastpresenterssync', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'creatorid');

        // Conditionally launch add field lastpresenterssync.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022011003, 'teammeeting');
    }

    if ($oldversion < 2022012700) {

        // Rename field creatorid on table teammeeting to usermodified.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('creatorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'timemodified');

        // Launch rename field creatorid.
        $dbman->rename_field($table, $field, 'usermodified');

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022012700, 'teammeeting');
    }

    if ($oldversion < 2022012701) {

        // Define field organiserid to be added to teammeeting.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('organiserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'reusemeeting');

        // Conditionally launch add field organiserid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022012701, 'teammeeting');
    }

    if ($oldversion < 2022012702) {

        // Changing nullability of field onlinemeetingid on table teammeeting to null.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('onlinemeetingid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'organiserid');

        // Launch change of nullability for field onlinemeetingid.
        $dbman->change_field_notnull($table, $field);

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022012702, 'teammeeting');
    }

    if ($oldversion < 2022012703) {

        // Changing nullability of field externalurl on table teammeeting to null.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('externalurl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'onlinemeetingid');

        // Launch change of nullability for field externalurl.
        $dbman->change_field_notnull($table, $field);

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022012703, 'teammeeting');
    }

    if ($oldversion < 2022022300) {

        // Define table teammeeting_meetings to be created.
        $table = new xmldb_table('teammeeting_meetings');

        // Adding fields to table teammeeting_meetings.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('teammeetingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('organiserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('onlinemeetingid', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('meetingurl', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('lastpresenterssync', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Adding keys to table teammeeting_meetings.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table teammeeting_meetings.
        $table->add_index('teammeetingidgroupid', XMLDB_INDEX_UNIQUE, ['teammeetingid', 'groupid']);

        // Conditionally launch create table for teammeeting_meetings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022022300, 'teammeeting');
    }

    if ($oldversion < 2022022301) {

        // Migrate to the newly created table.
        mod_teammeeting_migrate_to_meetings_table();

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022022301, 'teammeeting');
    }

    if ($oldversion < 2022030400) {

        // Define field groupid to be added to teammeeting.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'reusemeeting');

        // Conditionally launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022030400, 'teammeeting');
    }

    if ($oldversion < 2022090700) {

        // Define field allowchat to be added to teammeeting.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('allowchat', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'groupid');

        // Conditionally launch add field allowchat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022090700, 'teammeeting');
    }

    if ($oldversion < 2022091500) {

        // Define field attendeesmode to be added to teammeeting.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('attendeesmode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'allowchat');

        // Conditionally launch add field attendeesmode.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022091500, 'teammeeting');
    }

    if ($oldversion < 2022112300) {

        // Define field attendeesrole to be added to teammeeting.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('attendeesrole', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'attendeesmode');

        // Conditionally launch add field attendeesrole.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2022112300, 'teammeeting');
    }

    if ($oldversion < 2023011600) {

        // Define field teachersmode to be added to teammeeting.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('teachersmode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'attendeesrole');

        // Conditionally launch add field teachersmode.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2023011600, 'teammeeting');
    }

    if ($oldversion < 2023011601) {

        // Define field teacherids to be added to teammeeting.
        $table = new xmldb_table('teammeeting');
        $field = new xmldb_field('teacherids', XMLDB_TYPE_CHAR, '1024', null, XMLDB_NOTNULL, null, null, 'teachersmode');

        // Conditionally launch add field teacherids.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teammeeting savepoint reached.
        upgrade_mod_savepoint(true, 2023011601, 'teammeeting');
    }

    return true;
}
