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
 * Library hooks for local_timetable.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend user navigation with the timetable page.
 *
 * @param navigation_node $navigation
 */
function local_timetable_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();
    if (!has_capability('local/timetable:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/timetable/index.php');
    $navigation->add(
        get_string('pluginname', 'local_timetable'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_timetable'
    );
}

/**
 * Add the plugin link to the user's profile navigation tree.
 *
 * @param core_user\output\myprofile\tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 * @return void
 */
function local_timetable_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    ?stdClass $course
): void {
    if (!$iscurrentuser || !isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();
    if (!has_capability('local/timetable:view', $context)) {
        return;
    }

    $node = new \core_user\output\myprofile\node(
        'miscellaneous',
        'local_timetable',
        get_string('mytimetableprofile', 'local_timetable'),
        null,
        new \moodle_url('/local/timetable/index.php')
    );
    $tree->add_node($node);
}
