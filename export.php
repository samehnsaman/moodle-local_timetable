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
 * PDF export entry point.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

$context = context_system::instance();
require_capability('local/timetable:view', $context);

$timetableid = optional_param('timetableid', '', PARAM_ALPHANUMEXT);
$teacherfilter = optional_param('teacher', '', PARAM_RAW_TRIMMED);
$classfilter = optional_param('class', '', PARAM_RAW_TRIMMED);
$dayfilter = optional_param('day', '', PARAM_RAW_TRIMMED);

$service = new \local_timetable\local\timetable_service();
$payload = $service->get_user_timetable($USER, false, [
    'teacher' => $teacherfilter,
    'class' => $classfilter,
    'day' => $dayfilter,
]);

if ($timetableid !== '') {
    $payload['timetables'] = array_values(array_filter($payload['timetables'], function(array $timetable) use ($timetableid): bool {
        return (string)($timetable['id'] ?? '') === $timetableid;
    }));
}

$renderer = $PAGE->get_renderer('local_timetable');

$pdf = new pdf();
$pdf->SetTitle(get_string('pluginname', 'local_timetable'));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$timetables = $payload['timetables'] ?? [];
if (count($timetables) > 1) {
    $pdf->AddPage('L', 'A3');
    $pdf->writeHTML($renderer->render_pdf_document($payload), true, false, true, false, '');
}

foreach ($timetables as $timetable) {
    $pdf->AddPage('L', 'A3');
    $pdf->writeHTML($renderer->render_pdf_timetable_page($payload, $timetable), true, false, true, false, '');
}

$filename = get_string('pdffilename', 'local_timetable') . '.pdf';
$pdf->Output($filename, 'I');
exit;
