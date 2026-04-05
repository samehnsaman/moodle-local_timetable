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
 * Renderer for local_timetable.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetable\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for timetable pages.
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the main timetable body.
     *
     * @param array $payload
     * @return string
     */
    public function render_user_timetables(array $payload): string {
        $content = '';

        if (!empty($payload['request_debug'])) {
            $debug = $payload['request_debug'];
            $content .= \html_writer::div(
                \html_writer::tag('h4', s($debug['title'])) .
                \html_writer::tag('p', \html_writer::tag('strong', s(get_string('requestfilters', 'local_timetable')) . ': ') . s($payload['filter_summary_raw'] ?? '')) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['url_label']) . ': ') . s($debug['url'])) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['base_url_label']) . ': ') . s($debug['base_url'])) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['status_label']) . ': ') . s($debug['status'])) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['email_label']) . ': ') . s($debug['email'])) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['cohorts_label']) . ': ') . s($debug['cohorts'])) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['params_label']) . ':')) .
                \html_writer::tag('pre', s($debug['params_json'])) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['curl_label']) . ':')) .
                \html_writer::tag('pre', s($debug['curl'])) .
                \html_writer::tag('p', \html_writer::tag('strong', s($debug['response_label']) . ':')) .
                \html_writer::tag('pre', s($debug['response'])) .
                \html_writer::tag('p', s($debug['repro_text'])),
                'generalbox'
            );
        }

        $content .= $this->render_from_template('local_timetable/timetable', $payload);
        return $content;
    }

    /**
     * Build HTML suitable for PDF export.
     *
     * @param array $payload
     * @return string
     */
    public function render_pdf_document(array $payload): string {
        return $this->render_pdf_styles() . $this->render_pdf_cover($payload);
    }

    /**
     * Render a single PDF-first timetable page.
     *
     * @param array $payload
     * @param array $timetable
     * @return string
     */
    public function render_pdf_timetable_page(array $payload, array $timetable): string {
        $html = $this->render_pdf_styles();
        $html .= '<div class="cf-pdf-head">';
        $html .= '<div class="cf-pdf-logo">NRC</div>';
        $html .= '<div class="cf-pdf-brand">' . s($payload['school_name'] ?? 'Timetable Digital') . '</div>';
        $html .= '<div class="cf-pdf-subtitle">' . s($timetable['name'] ?? '') . '</div>';
        if (!empty($timetable['term_name'])) {
            $html .= '<div class="cf-pdf-subtitle-strong">' . s($timetable['term_name']) . '</div>';
        } else {
            $html .= '<div class="cf-pdf-subtitle-strong">' . s($payload['page_title'] ?? get_string('mytimetable', 'local_timetable')) . '</div>';
        }
        $html .= '</div>';

        $html .= '<table class="cf-pdf"><thead><tr><th class="cf-pdf-gutter-head">' . s(get_string('termlabel', 'local_timetable')) . '</th>';
        foreach ($timetable['columns'] ?? [] as $column) {
            $daylabel = s($column['label'] ?? '');
            $html .= '<th>' . $daylabel . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $rowcount = count($timetable['period_schedules'] ?? []);
        for ($row = 0; $row < $rowcount; $row++) {
            $schedule = $timetable['period_schedules'][$row];

            if (!empty($schedule['is_break'])) {
                $html .= '<tr>';
                $html .= '<td class="cf-pdf-gutter cf-pdf-gutter-break">' . s($schedule['name'] ?? '') . '</td>';
                $html .= '<td class="cf-pdf-break-row" colspan="' . count($timetable['columns'] ?? []) . '"><span>' . s($schedule['name'] ?? '') . '</span></td>';
                $html .= '</tr>';
                continue;
            }

            $html .= '<tr>';
            $html .= '<td class="cf-pdf-gutter">';
            $html .= '<div class="cf-pdf-period-label">' . s($schedule['name'] ?? '') . '</div>';
            if (!empty($schedule['start_time']) || !empty($schedule['end_time'])) {
                $times = [];
                if (!empty($schedule['start_time'])) {
                    $times[] = s($schedule['start_time']);
                }
                if (!empty($schedule['end_time'])) {
                    $times[] = s(substr((string)$schedule['end_time'], 0, 5));
                }
                $html .= '<div class="cf-pdf-period-time">' . implode(' - ', $times) . '</div>';
            }
            $html .= '</td>';

            foreach ($timetable['columns'] ?? [] as $column) {
                $period = $column['periods'][$row] ?? [];
                $html .= '<td class="cf-pdf-slot">';
                $entries = $period['entries'] ?? [];
                if (empty($entries)) {
                    $html .= '<div class="cf-pdf-empty"></div>';
                }

                $entrycount = count($entries);
                foreach ($entries as $entryindex => $entry) {
                    $entryclass = $entryindex === ($entrycount - 1) ? ' cf-pdf-entry-last' : '';
                    $html .= '<div class="cf-pdf-entry-wrap"><div class="cf-pdf-entry' . $entryclass . '" style="border-left-color:' . s($entry['color'] ?? '#1e293b') . '; background-color:' . s($entry['pdf_bg_color'] ?? '#ffffff') . ';">';
                    $html .= '<div class="cf-pdf-entry-top" style="color:' . s($entry['color'] ?? '#1e293b') . ';">' . s($entry['subject_name'] ?? '') . '</div>';
                    $metaparts = [];
                    if (!empty($entry['teacher_name'])) {
                        $metaparts[] = $entry['teacher_name'];
                    }
                    if (!empty($entry['class_name'])) {
                        $metaparts[] = $entry['class_name'];
                    }
                    if (!empty($entry['show_room']) && !empty($entry['classroom_name'])) {
                        $metaparts[] = $entry['classroom_name'];
                    }
                    if (!empty($metaparts)) {
                        $html .= '<div class="cf-pdf-entry-meta">' . s(implode(' | ', $metaparts)) . '</div>';
                    }
                    $html .= '</div></div>';
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="cf-pdf-footer">' . s(get_string('poweredby', 'local_timetable')) . ' (' . s(get_string('poweredbyurl', 'local_timetable')) . ') - ' . s(userdate(time(), '%d/%m/%Y')) . '</div>';
        return $html;
    }

    /**
     * Shared TCPDF-friendly PDF styles.
     *
     * @return string
     */
    private function render_pdf_styles(): string {
        return '<style>
            body { font-family: dejavusans, sans-serif; color: #1f2937; font-size: 9px; }
            .cf-pdf-head { text-align: center; margin: 2px 0 12px; }
            .cf-pdf-logo { font-size: 15px; font-weight: bold; color: #3142a0; margin-bottom: 3px; }
            .cf-pdf-brand { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
            .cf-pdf-subtitle { font-size: 10px; color: #6b7280; margin-bottom: 2px; }
            .cf-pdf-subtitle-strong { font-size: 12px; font-weight: bold; color: #374151; }
            table.cf-pdf { width: 100%; border-collapse: collapse; table-layout: fixed; }
            table.cf-pdf th { background-color: #f8fafc; color: #273244; font-size: 9px; font-weight: bold; text-align: center; padding: 6px 5px; border: 1px solid #d7dee7; }
            table.cf-pdf th.cf-pdf-gutter-head { width: 118px; text-align: left; }
            table.cf-pdf td { vertical-align: top; padding: 0; border: 1px solid #d7dee7; background-color: #ffffff; }
            table.cf-pdf td.cf-pdf-gutter { width: 118px; padding: 6px 6px 4px; background-color: #fafbfc; }
            table.cf-pdf td.cf-pdf-gutter-break { padding: 6px; font-weight: bold; color: #4b5563; background-color: #fff8db; }
            .cf-pdf-period-label { font-size: 8.6px; font-weight: bold; line-height: 1.2; }
            .cf-pdf-period-time { font-size: 7.4px; color: #7b8794; margin-top: 2px; }
            td.cf-pdf-slot { padding: 3px; height: 42px; vertical-align: top; }
            td.cf-pdf-break-row { background-color: #fff3c4; color: #9a6d00; text-align: center; vertical-align: middle; font-style: italic; font-size: 9px; font-weight: 600; }
            td.cf-pdf-break-row span { display: inline-block; padding: 4px 0; }
            .cf-pdf-empty { height: 28px; }
            div.cf-pdf-entry { border-left: 3px solid #9ca3af; background-color: #ffffff; padding: 5px 6px 4px; margin: 0; }
            div.cf-pdf-entry-last { margin-bottom: 0; }
            .cf-pdf-entry-top { font-size: 9.3px; font-weight: bold; line-height: 1.15; margin-bottom: 1px; }
            .cf-pdf-entry-meta { font-size: 7.5px; line-height: 1.15; color: #6b7280; white-space: nowrap; }
            .cf-pdf-footer { text-align: center; color: #9ca3af; font-size: 7px; margin-top: 10px; }
            .cf-pdf-cover { text-align: center; margin-top: 120px; }
            .cf-pdf-cover-title { font-size: 22px; font-weight: bold; margin-bottom: 6px; }
            .cf-pdf-cover-meta { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
            .cf-pdf-entry-wrap { width: 100%; }
        </style>';
    }

    /**
     * Cover page summary when exporting multiple timetables.
     *
     * @param array $payload
     * @return string
     */
    private function render_pdf_cover(array $payload): string {
        $html = '<div class="cf-pdf-cover">';
        $html .= '<div class="cf-pdf-logo">NRC</div>';
        $html .= '<div class="cf-pdf-cover-title">' . s($payload['page_title'] ?? get_string('mytimetable', 'local_timetable')) . '</div>';
        if (!empty($payload['school_name'])) {
            $html .= '<div class="cf-pdf-cover-meta">' . s($payload['school_name']) . '</div>';
        }
        $html .= '<div class="cf-pdf-cover-meta">' . s(get_string('pdfgenerated', 'local_timetable')) . ': ' .
            s(userdate(time(), get_string('strftimedatetime', 'langconfig'))) . '</div>';
        $html .= '</div>';
        return $html;
    }
}
