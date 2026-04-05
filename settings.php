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
 * Settings for local_timetable.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_timetable', get_string('pluginname', 'local_timetable'));

    $settings->add(new admin_setting_heading(
        'local_timetable/setupinfo',
        get_string('settings:setupinfo', 'local_timetable'),
        get_string('settings:setupinfo_desc', 'local_timetable')
    ));

    $settings->add(new admin_setting_configtext(
        'local_timetable/api_url',
        get_string('settings:apiurl', 'local_timetable'),
        get_string('settings:apiurl_desc', 'local_timetable'),
        'https://api.timetable.digital/functions/v1/public-api',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_timetable/api_key',
        get_string('settings:apikey', 'local_timetable'),
        get_string('settings:apikey_desc', 'local_timetable'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_timetable/origin',
        get_string('settings:origin', 'local_timetable'),
        get_string('settings:origin_desc', 'local_timetable'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_timetable/request_timeout',
        get_string('settings:timeout', 'local_timetable'),
        get_string('settings:timeout_desc', 'local_timetable'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'local_timetable/course_identifier_mode',
        get_string('settings:courseidentifiermode', 'local_timetable'),
        get_string('settings:courseidentifiermode_desc', 'local_timetable'),
        'idnumber',
        [
            'idnumber' => get_string('settings:courseidentifiermode_idnumber', 'local_timetable'),
            'id' => get_string('settings:courseidentifiermode_id', 'local_timetable'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_timetable/debugenabled',
        get_string('settings:debugenabled', 'local_timetable'),
        get_string('settings:debugenabled_desc', 'local_timetable'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}
