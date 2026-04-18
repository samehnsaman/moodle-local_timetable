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
 * Timetable page entry point.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/timetable:view', $context);

$refresh = optional_param('refresh', 0, PARAM_BOOL);
$teacherfilter = optional_param('teacher', '', PARAM_RAW_TRIMMED);
$classfilter = optional_param('class', '', PARAM_RAW_TRIMMED);
$dayfilter = optional_param('day', '', PARAM_RAW_TRIMMED);

$PAGE->set_url(new moodle_url('/local/timetable/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_timetable'));
$PAGE->set_heading(get_string('pluginname', 'local_timetable'));

if ($refresh) {
    require_sesskey();
}

$service = new \local_timetable\local\timetable_service();
$renderer = $PAGE->get_renderer('local_timetable');
$debugenabled = (bool)get_config('local_timetable', 'debugenabled');

try {
    $payload = $service->get_user_timetable($USER, $refresh, [
        'teacher' => $teacherfilter,
        'class' => $classfilter,
        'day' => $dayfilter,
    ]);
} catch (\moodle_exception $exception) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($exception->getMessage(), 'notifyproblem');
    if ($debugenabled && $exception->errorcode === 'nocacheavailable') {
        $debug = is_array($exception->a) ? $exception->a : [];
        if (!empty($debug)) {
            echo $OUTPUT->box(
                \html_writer::tag('h4', s($debug['title'] ?? get_string('requestdebug', 'local_timetable'))) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['url_label'] ?? get_string('requesturl', 'local_timetable')) . ': ') . s($debug['url'] ?? '')) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['base_url_label'] ?? get_string('requestbaseurl', 'local_timetable')) . ': ') . s($debug['base_url'] ?? '')) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['status_label'] ?? get_string('requeststatus', 'local_timetable')) . ': ') . s($debug['status'] ?? '0')) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['email_label'] ?? get_string('requestemail', 'local_timetable')) . ': ') . s($debug['email'] ?? '')) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['cohorts_label'] ?? get_string('requestcohorts', 'local_timetable')) . ': ') . s($debug['cohorts'] ?? '')) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['params_label'] ?? get_string('requestparams', 'local_timetable')) . ':')) .
                \html_writer::tag('pre', s($debug['params_json'] ?? '')) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['curl_label'] ?? get_string('requestcurl', 'local_timetable')) . ':')) .
                \html_writer::tag('pre', s($debug['curl'] ?? '')) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['response_label'] ?? get_string('requestresponse', 'local_timetable')) . ':')) .
                \html_writer::tag('pre', s($debug['response'] ?? '')) .
                \html_writer::tag('p', s($debug['repro_text'] ?? get_string('requestrepro', 'local_timetable'))),
                'generalbox'
            );
        }
    }
    echo $OUTPUT->footer();
    exit;
}

$refreshurl = new moodle_url('/local/timetable/index.php', [
    'refresh' => 1,
    'teacher' => $teacherfilter,
    'class' => $classfilter,
    'day' => $dayfilter,
    'sesskey' => sesskey(),
]);

foreach ($payload['timetables'] as $index => $timetable) {
    $payload['timetables'][$index]['refresh_url'] = $refreshurl->out(false);
    $payload['timetables'][$index]['refresh_title'] = get_string('refreshshort', 'local_timetable');
    $payload['timetables'][$index]['pdf_url'] = (new moodle_url('/local/timetable/export.php', [
        'timetableid' => $timetable['id'],
        'teacher' => $teacherfilter,
        'class' => $classfilter,
        'day' => $dayfilter,
    ]))->out(false);
    $payload['timetables'][$index]['pdf_title'] = get_string('exportpdf_full', 'local_timetable');
}
    $payload['powered_by'] = get_string('poweredby', 'local_timetable');
    $payload['powered_by_url'] = get_string('poweredbyurl', 'local_timetable');
    $payload['clear_filters_url'] = (new moodle_url('/local/timetable/index.php'))->out(false);

echo $OUTPUT->header();

if (!empty($payload['notice'])) {
    echo $OUTPUT->notification($payload['notice'], 'warning');
}

echo $renderer->render_user_timetables($payload);
echo $OUTPUT->footer();
