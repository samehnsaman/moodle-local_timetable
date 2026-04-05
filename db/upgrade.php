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
 * Upgrade steps for local_timetable.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_timetable_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026040121) {
        $newtable = new xmldb_table('local_timetable_cache');

        if (!$dbman->table_exists($newtable)) {
            $newtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $newtable->add_field('cachekey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $newtable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $newtable->add_field('useremail', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $newtable->add_field('cohortkeys', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $newtable->add_field('responsejson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $newtable->add_field('versionsjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $newtable->add_field('timefetched', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $newtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $newtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $newtable->add_index('cachekey_uix', XMLDB_INDEX_UNIQUE, ['cachekey']);
            $newtable->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($newtable);
        }

        $oldtable = new xmldb_table('local_classflow_cache');
        if ($dbman->table_exists($oldtable) && $dbman->table_exists($newtable) && !$DB->record_exists('local_timetable_cache', [])) {
            $records = $DB->get_records('local_classflow_cache');
            foreach ($records as $record) {
                unset($record->id);
                $DB->insert_record('local_timetable_cache', $record);
            }
        }

        upgrade_plugin_savepoint(true, 2026040121, 'local', 'timetable');
    }

    return true;
}
