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
 * Privacy provider for local_timetable.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetable\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider implementation for local_timetable.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_user_data_provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the data stored and sent by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_timetable_cache', [
            'userid' => 'privacy:metadata:local_timetable_cache:userid',
            'useremail' => 'privacy:metadata:local_timetable_cache:useremail',
            'cohortkeys' => 'privacy:metadata:local_timetable_cache:cohortkeys',
            'responsejson' => 'privacy:metadata:local_timetable_cache:responsejson',
            'versionsjson' => 'privacy:metadata:local_timetable_cache:versionsjson',
            'timefetched' => 'privacy:metadata:local_timetable_cache:timefetched',
            'timemodified' => 'privacy:metadata:local_timetable_cache:timemodified',
        ], 'privacy:metadata:local_timetable_cache');

        $collection->add_external_location_link('timetable_digital', [
            'email' => 'privacy:metadata:timetable_digital:email',
            'class_external_id' => 'privacy:metadata:timetable_digital:class_external_id',
        ], 'privacy:metadata:timetable_digital');

        return $collection;
    }

    /**
     * Get contexts containing user data for the supplied user id.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($DB->record_exists('local_timetable_cache', ['userid' => $userid])) {
            $contextlist->add_context(context_system::instance());
        }

        return $contextlist;
    }

    /**
     * Export user data for the supplied contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $systemcontext = context_system::instance();

        if (!in_array($systemcontext->id, $contextlist->get_contextids(), true)) {
            return;
        }

        $records = $DB->get_records('local_timetable_cache', ['userid' => $userid], 'timefetched ASC');
        if (!$records) {
            return;
        }

        $writer = writer::with_context($systemcontext);
        foreach ($records as $record) {
            $export = (object)[
                'useremail' => $record->useremail,
                'cohortkeys' => $record->cohortkeys,
                'responsejson' => $record->responsejson,
                'versionsjson' => $record->versionsjson,
                'timefetched' => transform::datetime($record->timefetched),
                'timemodified' => transform::datetime($record->timemodified),
            ];
            $writer->export_data(['cache', $record->cachekey], $export);
        }
    }

    /**
     * Delete all user data in the specified context.
     *
     * @param context $context The context to delete data from.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_system) {
            return;
        }

        $DB->delete_records('local_timetable_cache');
    }

    /**
     * Delete all user data for the specified user.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $systemcontext = context_system::instance();
        if (!in_array($systemcontext->id, $contextlist->get_contextids(), true)) {
            return;
        }

        $DB->delete_records('local_timetable_cache', ['userid' => $contextlist->get_user()->id]);
    }

    /**
     * Get all users who have data in the supplied context.
     *
     * @param userlist $userlist The userlist object.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }

        $sql = "SELECT userid
                  FROM {local_timetable_cache}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Delete data for the users in the supplied context/userlist.
     *
     * @param approved_userlist $userlist The approved userlist.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_timetable_cache', "userid {$insql}", $params);
    }
}
