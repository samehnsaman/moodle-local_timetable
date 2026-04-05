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
 * Timetable service logic.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetable\local;

defined('MOODLE_INTERNAL') || die();

use local_timetable\api_client;

/**
 * Builds user-specific timetable queries, manages persistence, and prepares template context.
 */
class timetable_service {
    /** @var array */
    private $fallbackcolors = ['#6366f1', '#22c55e', '#f97316', '#a855f7', '#f43f5e', '#14b8a6'];

    /**
     * Return the current user's timetable payload ready for rendering.
     *
     * @param \stdClass $user
     * @param bool $forcefresh
     * @return array
     * @throws \moodle_exception
     */
    public function get_user_timetable($user, bool $forcefresh = false, array $viewfilters = []): array {
        global $SESSION;

        $user = $this->resolve_user_record($user);
        $debugenabled = (bool)get_config('local_timetable', 'debugenabled');
        $filters = $this->build_filters($user);
        if (empty($filters['params'])) {
            throw new \moodle_exception('nofiltersavailable', 'local_timetable');
        }

        $cachekey = $filters['cachekey'];
        $cachedrecord = $this->get_cached_record($cachekey);

        if (!$forcefresh && !empty($SESSION->local_timetable_checked[$cachekey]) && $cachedrecord) {
            return $this->build_render_payload(
                $this->decode_json($cachedrecord->responsejson),
                $filters,
                false,
                $viewfilters
            );
        }

        $client = new api_client();
        $requestdebug = $debugenabled ? $this->build_request_debug($client, $filters) : [];
        try {
            $remoteresponse = $client->get_timetable($filters['params']);
        } catch (\moodle_exception $exception) {
            $requestdebug = $debugenabled ? $this->build_request_debug($client, $filters) : [];
            if ($cachedrecord) {
                $SESSION->local_timetable_checked[$cachekey] = true;
                $payload = $this->build_render_payload(
                    $this->decode_json($cachedrecord->responsejson),
                    $filters,
                    true,
                    $viewfilters
                );
                $payload['notice'] = get_string('refreshedusingcache', 'local_timetable');
                if ($debugenabled) {
                    $payload['request_debug'] = $requestdebug;
                }
                return $payload;
            }
            throw new \moodle_exception('nocacheavailable', 'local_timetable', '', $debugenabled ? $requestdebug : null);
        }

        $remoteversions = $this->extract_versions($remoteresponse);
        $cachedversions = $cachedrecord ? $this->decode_json($cachedrecord->versionsjson) : [];
        $requestdebug = $debugenabled ? $this->build_request_debug($client, $filters) : [];

        if ($cachedrecord && $this->versions_match($cachedversions, $remoteversions) && !$forcefresh) {
            $SESSION->local_timetable_checked[$cachekey] = true;
            $payload = $this->build_render_payload(
                $this->decode_json($cachedrecord->responsejson),
                $filters,
                false,
                $viewfilters
            );
            if ($debugenabled) {
                $payload['request_debug'] = $requestdebug;
            }
            return $payload;
        }

        $this->store_cached_record($cachekey, $user, $filters, $remoteresponse, $remoteversions, $cachedrecord);
        $SESSION->local_timetable_checked[$cachekey] = true;

        $payload = $this->build_render_payload($remoteresponse, $filters, false, $viewfilters);
        if ($debugenabled) {
            $payload['request_debug'] = $requestdebug;
        }
        return $payload;
    }

    /**
     * Build request params from Moodle identity and cohorts.
     *
     * @param \stdClass $user
     * @return array
     */
    private function build_filters(\stdClass $user): array {
        $email = trim(\core_text::strtolower((string)($user->email ?? '')));
        if (!validate_email($email)) {
            $email = '';
        }

        $cohortids = $this->get_user_cohort_idnumbers((int)$user->id);
        $params = [];
        $parts = [];

        if ($email !== '') {
            $params['email'] = $email;
            $parts[] = 'email:' . $email;
        }

        if (!empty($cohortids)) {
            $params['class_external_id'] = implode(',', $cohortids);
            $parts[] = 'cohorts:' . implode(',', $cohortids);
        }

        return [
            'email' => $email,
            'cohortids' => $cohortids,
            'params' => $params,
            'cachekey' => hash('sha256', implode('|', $parts)),
            'summary' => implode(' | ', $parts),
        ];
    }

    /**
     * Load cohort idnumbers linked to the user.
     *
     * @param int $userid
     * @return array
     */
    private function get_user_cohort_idnumbers(int $userid): array {
        global $DB;

        $sql = "SELECT c.idnumber
                  FROM {cohort} c
                  JOIN {cohort_members} cm ON cm.cohortid = c.id
                 WHERE cm.userid = :userid
                   AND c.idnumber IS NOT NULL
                   AND c.idnumber <> :emptyidnumber";

        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'emptyidnumber' => '',
        ]);

        $values = [];
        foreach ($records as $record) {
            $value = trim((string)$record->idnumber);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        $values = array_values(array_unique($values));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return $values;
    }

    /**
     * Fetch cached payload row.
     *
     * @param string $cachekey
     * @return \stdClass|false
     */
    private function get_cached_record(string $cachekey) {
        global $DB;

        return $DB->get_record('local_timetable_cache', ['cachekey' => $cachekey]);
    }

    /**
     * Persist the latest response in Moodle DB.
     *
     * @param string $cachekey
     * @param \stdClass $user
     * @param array $filters
     * @param array $response
     * @param array $versions
     * @param \stdClass|false $existing
     * @return void
     */
    private function store_cached_record(
        string $cachekey,
        \stdClass $user,
        array $filters,
        array $response,
        array $versions,
        $existing
    ): void {
        global $DB;

        $now = time();
        $record = (object)[
            'cachekey' => $cachekey,
            'userid' => (int)$user->id,
            'useremail' => $filters['email'] ?: null,
            'cohortkeys' => json_encode($filters['cohortids']),
            'responsejson' => json_encode($response),
            'versionsjson' => json_encode($versions),
            'timefetched' => $now,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_timetable_cache', $record);
            return;
        }

        $DB->insert_record('local_timetable_cache', $record);
    }

    /**
     * Map timetable updated_at values.
     *
     * @param array $response
     * @return array
     */
    private function extract_versions(array $response): array {
        $versions = [];
        foreach ($response['timetables'] ?? [] as $timetable) {
            if (!empty($timetable['id'])) {
                $versions[$timetable['id']] = $timetable['updated_at'] ?? '';
            }
        }
        ksort($versions);
        return $versions;
    }

    /**
     * Compare stored and remote version maps.
     *
     * @param array $cached
     * @param array $remote
     * @return bool
     */
    private function versions_match(array $cached, array $remote): bool {
        ksort($cached);
        ksort($remote);
        return $cached === $remote;
    }

    /**
     * Safely decode cached JSON blobs.
     *
     * @param string $json
     * @return array
     */
    private function decode_json(string $json): array {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Prepare the full template payload.
     *
     * @param array $response
     * @param array $filters
     * @param bool $isstale
     * @return array
     */
    private function build_render_payload(array $response, array $filters, bool $isstale, array $viewfilters = []): array {
        $schoolname = $response['school'] ?? '';
        $timetables = [];
        $index = 0;
        $isadmin = !empty($response['is_admin']);
        $selectedteacher = trim((string)($viewfilters['teacher'] ?? ''));
        $selectedclass = trim((string)($viewfilters['class'] ?? ''));
        $selectedday = trim((string)($viewfilters['day'] ?? ''));
        $filteroptions = $this->build_filter_options($response, $selectedteacher, $selectedclass);
        $dayoptions = $this->build_day_options($response, $selectedday);

        foreach ($response['timetables'] ?? [] as $timetable) {
            $timetableisadmin = $isadmin || !empty($timetable['is_admin']);
            if ($timetableisadmin) {
                $timetable = $this->apply_admin_filters($timetable, $selectedteacher, $selectedclass);
            }

            $prepared = $this->prepare_timetable_context($timetable, $isstale, $timetableisadmin, $selectedday);
            $prepared['tab_id'] = 'cf-tab-' . $index;
            $prepared['panel_id'] = 'cf-panel-' . $index;
            $prepared['is_active'] = $index === 0;
            $timetables[] = $prepared;
            $index++;
        }

        return [
            'page_title' => get_string('mytimetable', 'local_timetable'),
            'school_name' => $schoolname,
            'filter_summary' => get_string('filtersummary', 'local_timetable', $filters['summary']),
            'filter_summary_raw' => $filters['summary'],
            'is_admin' => $isadmin,
            'admin_label' => get_string('adminview', 'local_timetable'),
            'has_multiple_timetables' => count($timetables) > 1,
            'teacher_filter_label' => get_string('filterteacher', 'local_timetable'),
            'class_filter_label' => get_string('filterclass', 'local_timetable'),
            'day_filter_label' => get_string('filterday', 'local_timetable'),
            'teacher_options' => $filteroptions['teachers'],
            'class_options' => $filteroptions['classes'],
            'day_options' => $dayoptions,
            'selected_teacher' => $selectedteacher,
            'selected_class' => $selectedclass,
            'selected_day' => $selectedday,
            'apply_filters_label' => get_string('applyfilters', 'local_timetable'),
            'clear_filters_label' => get_string('clearfilters', 'local_timetable'),
            'timetables' => $timetables,
            'empty_message' => get_string('notimetables', 'local_timetable'),
            'stale' => $isstale,
        ];
    }

    /**
     * Build a user-safe request debug block.
     *
     * @param api_client $client
     * @param array $filters
     * @return array
     */
    private function build_request_debug(api_client $client, array $filters): array {
        return [
            'title' => get_string('requestdebug', 'local_timetable'),
            'url_label' => get_string('requesturl', 'local_timetable'),
            'base_url_label' => get_string('requestbaseurl', 'local_timetable'),
            'params_label' => get_string('requestparams', 'local_timetable'),
            'email_label' => get_string('requestemail', 'local_timetable'),
            'cohorts_label' => get_string('requestcohorts', 'local_timetable'),
            'status_label' => get_string('requeststatus', 'local_timetable'),
            'response_label' => get_string('requestresponse', 'local_timetable'),
            'curl_label' => get_string('requestcurl', 'local_timetable'),
            'repro_text' => get_string('requestrepro', 'local_timetable'),
            'url' => $client->get_last_debug()['url'] ?: $client->build_timetable_url($filters['params']),
            'base_url' => $client->get_timetable_endpoint(),
            'params_json' => json_encode($filters['params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'email' => $filters['email'] !== '' ? $filters['email'] : get_string('requestnone', 'local_timetable'),
            'cohorts' => !empty($filters['cohortids']) ? implode(', ', $filters['cohortids']) : get_string('requestnone', 'local_timetable'),
            'status' => (string)($client->get_last_debug()['status'] ?: 0),
            'response' => $client->get_last_debug()['response'] !== '' ? $client->get_last_debug()['response'] : get_string('requestnone', 'local_timetable'),
            'curl' => $this->build_curl_repro($client->get_timetable_endpoint(), $filters['params']),
        ];
    }

    /**
     * Build a copy-paste curl command without exposing the API key.
     *
     * @param string $endpoint
     * @param array $params
     * @return string
     */
    private function build_curl_repro(string $endpoint, array $params): string {
        $url = $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return 'curl "' . $url . '" \\' . "\n" .
            '  -H "x-api-key: YOUR_KEY" \\' . "\n" .
            '  -H "Accept: application/json"';
    }

    /**
     * Convert one API timetable to the Mustache view model.
     *
     * @param array $timetable
     * @param bool $isstale
     * @return array
     */
    private function prepare_timetable_context(array $timetable, bool $isstale, bool $isadmin = false, string $selectedday = ''): array {
        $courseidentifiermode = (string)get_config('local_timetable', 'course_identifier_mode');
        $coursecache = [];
        $daylabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $todayindex = (int)date('N') - 1;

        $workingdays = $timetable['working_days'] ?? [];
        sort($workingdays);
        if ($selectedday !== '' && ctype_digit($selectedday)) {
            $selecteddayindex = (int)$selectedday;
            $workingdays = array_values(array_filter($workingdays, function(int $day) use ($selecteddayindex): bool {
                return $day === $selecteddayindex;
            }));
        }

        $entriesmap = [];
        foreach ($timetable['entries'] ?? [] as $entry) {
            $day = (int)($entry['day_of_week'] ?? -1);
            $period = (int)($entry['period_number'] ?? 0);
            if ($day >= 0 && $period > 0) {
                $entriesmap[$day][$period][] = $entry;
            }
        }

        $schedulebyday = [];
        $defaultschedules = [];
        foreach ($timetable['period_schedules'] ?? [] as $schedule) {
            $period = (int)($schedule['period_number'] ?? 0);
            if ($period <= 0) {
                continue;
            }

            if (array_key_exists('day_of_week', $schedule) && $schedule['day_of_week'] !== null) {
                $schedulebyday[(int)$schedule['day_of_week']][$period] = $schedule;
            } else {
                $defaultschedules[$period] = $schedule;
            }
        }

        if (empty($defaultschedules)) {
            foreach ($schedulebyday as $dayschedules) {
                foreach ($dayschedules as $period => $schedule) {
                    $defaultschedules[$period] = $schedule;
                }
            }
        }

        ksort($defaultschedules);
        $periodschedules = [];
        foreach ($defaultschedules as $period => $schedule) {
            $periodlabel = $schedule['display_name'] ?? $schedule['name'] ?? ('P' . $period);
            $starttime = substr((string)($schedule['start_time'] ?? ''), 0, 5);
            $endtime = substr((string)($schedule['end_time'] ?? ''), 0, 5);
            $iscurrentperiod = $this->is_current_period($starttime, $endtime, $workingdays, $todayindex);
            $periodschedules[] = [
                'period_number' => $period,
                'name' => $periodlabel,
                'start_time' => $starttime,
                'end_time' => $endtime,
                'is_break' => !empty($schedule['is_break']),
                'is_current_period' => $iscurrentperiod,
            ];
        }

        $columns = [];
        foreach ($workingdays as $dayindex) {
            $column = [
                'label' => $daylabels[$dayindex] ?? ('Day ' . $dayindex),
                'is_today' => $dayindex === $todayindex,
                'periods' => [],
            ];

            foreach ($defaultschedules as $period => $fallbackschedule) {
                $schedule = $schedulebyday[$dayindex][$period] ?? $fallbackschedule;
                $cellentries = $entriesmap[$dayindex][$period] ?? [];
                $issingle = count($cellentries) === 1;
                $starttime = substr((string)($schedule['start_time'] ?? ''), 0, 5);
                $endtime = substr((string)($schedule['end_time'] ?? ''), 0, 5);
                $iscurrentperiod = $this->is_current_period($starttime, $endtime, $workingdays, $todayindex);

                $renderedentries = [];
                foreach ($cellentries as $entry) {
                    $color = $entry['color']
                        ?? $entry['subjects']['color_code']
                        ?? $entry['classes']['color_code']
                        ?? $entry['subjects']['color_code']
                        ?? $this->fallbackcolors[((int)($entry['subjects']['color_index'] ?? 0)) % count($this->fallbackcolors)];

                    $gradename = $entry['classes']['grades']['name'] ?? '';
                    $classname = $entry['classes']['name'] ?? '';
                    $classdisplay = $gradename !== '' ? $gradename . ' - ' . $classname : $classname;
                    $isonline = !empty($entry['is_online']);
                    $meetinglink = trim((string)($entry['meeting_link'] ?? ''));
                    $courseinfo = $this->resolve_course_link((string)($entry['external_course_id'] ?? ''), $courseidentifiermode, $coursecache);
                    $titleparts = array_filter([
                        $entry['subjects']['name'] ?? '',
                        $entry['teachers']['name'] ?? '',
                        $classdisplay,
                        $entry['classrooms']['name'] ?? '',
                        $isonline ? get_string('onlinesession', 'local_timetable') : '',
                        !empty($courseinfo['label']) ? get_string('course', 'local_timetable') . ': ' . $courseinfo['label'] : '',
                    ]);

                    $renderedentries[] = [
                        'subject_name' => $entry['subjects']['name'] ?? '',
                        'teacher_name' => $entry['teachers']['name'] ?? '',
                        'class_name' => $classdisplay,
                        'compact_meta' => $classdisplay,
                        'classroom_name' => $entry['classrooms']['name'] ?? '',
                        'color' => $color,
                        'bg_color' => $color . '18',
                        'pdf_bg_color' => $this->fade_hex_color($color, 0.92),
                        'border_color' => $color . '40',
                        'show_room' => $issingle && !empty($entry['classrooms']['name']),
                        'show_teacher' => count($cellentries) <= 2 && !empty($entry['teachers']['name']),
                        'is_online' => $isonline,
                        'online_label' => get_string('onlinesession', 'local_timetable'),
                        'has_meeting_link' => $isonline && $meetinglink !== '',
                        'meeting_link' => $meetinglink,
                        'meeting_label' => get_string('joinmeeting', 'local_timetable'),
                        'has_course_link' => !empty($courseinfo['url']),
                        'course_url' => $courseinfo['url'] ?? '',
                        'course_label' => $courseinfo['label'] ?? get_string('course', 'local_timetable'),
                        'title_text' => implode(' | ', $titleparts),
                    ];
                }

                $isbreak = !empty($schedule['is_break']);
                $breaklabel = $schedule['display_name'] ?? $schedule['name'] ?? get_string('break', 'local_timetable');
                $column['periods'][] = [
                    'period_number' => $period,
                    'is_break' => $isbreak,
                    'break_label' => $isbreak ? $breaklabel : '',
                    'has_entries' => !empty($renderedentries),
                    'is_single' => $issingle,
                    'is_current_period' => $iscurrentperiod,
                    'is_admin' => $isadmin,
                    'entries' => $renderedentries,
                ];
            }

            $columns[] = $column;
        }

        return [
            'id' => $timetable['id'] ?? '',
            'name' => $timetable['name'] ?? '',
            'term_name' => $timetable['term']['name'] ?? '',
            'term_label' => get_string('termlabel', 'local_timetable'),
            'period_schedules' => $periodschedules,
            'columns' => $columns,
            'grid_columns' => 'repeat(' . max(count($columns), 1) . ', minmax(0, 1fr))',
            'is_admin' => $isadmin,
            'is_stale' => $isstale,
            'stale_label' => get_string('staleindicator', 'local_timetable'),
        ];
    }

    /**
     * Check whether the current server time falls inside a period.
     *
     * @param string $starttime
     * @param string $endtime
     * @param array $workingdays
     * @param int $todayindex
     * @return bool
     */
    private function is_current_period(string $starttime, string $endtime, array $workingdays, int $todayindex): bool {
        if ($starttime === '' || $endtime === '' || !in_array($todayindex, $workingdays, true)) {
            return false;
        }

        $now = date('H:i');
        return $starttime <= $now && $now < $endtime;
    }

    /**
     * Build filter option lists for admin responses.
     *
     * @param array $response
     * @return array
     */
    private function build_filter_options(array $response, string $selectedteacher = '', string $selectedclass = ''): array {
        $teachers = [];
        $classes = [];

        foreach ($response['timetables'] ?? [] as $timetable) {
            foreach ($timetable['entries'] ?? [] as $entry) {
                $teacherid = (string)($entry['teachers']['id'] ?? '');
                $teachername = trim((string)($entry['teachers']['name'] ?? ''));
                if ($teacherid !== '' && $teachername !== '') {
                    $teachers[$teacherid] = $teachername;
                }

                $classid = (string)($entry['classes']['id'] ?? '');
                $gradename = trim((string)($entry['classes']['grades']['name'] ?? ''));
                $classname = trim((string)($entry['classes']['name'] ?? ''));
                $classlabel = $gradename !== '' ? $gradename . ' - ' . $classname : $classname;
                if ($classid !== '' && $classlabel !== '') {
                    $classes[$classid] = $classlabel;
                }
            }
        }

        natcasesort($teachers);
        natcasesort($classes);

        $teacheroptions = [[
            'value' => '',
            'label' => get_string('filterallteachers', 'local_timetable'),
            'selected' => $selectedteacher === '',
        ]];
        foreach ($teachers as $value => $label) {
            $teacheroptions[] = ['value' => $value, 'label' => $label, 'selected' => $selectedteacher === (string)$value];
        }

        $classoptions = [[
            'value' => '',
            'label' => get_string('filterallclasses', 'local_timetable'),
            'selected' => $selectedclass === '',
        ]];
        foreach ($classes as $value => $label) {
            $classoptions[] = ['value' => $value, 'label' => $label, 'selected' => $selectedclass === (string)$value];
        }

        return [
            'teachers' => $teacheroptions,
            'classes' => $classoptions,
        ];
    }

    /**
     * Build day filter options from working days across returned timetables.
     *
     * @param array $response
     * @param string $selectedday
     * @return array
     */
    private function build_day_options(array $response, string $selectedday = ''): array {
        $daylabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $workingdays = [];

        foreach ($response['timetables'] ?? [] as $timetable) {
            foreach ($timetable['working_days'] ?? [] as $day) {
                $workingdays[(int)$day] = true;
            }
        }

        ksort($workingdays);
        $options = [[
            'value' => '',
            'label' => get_string('filteralldays', 'local_timetable'),
            'selected' => $selectedday === '',
        ]];

        foreach (array_keys($workingdays) as $day) {
            $options[] = [
                'value' => (string)$day,
                'label' => $daylabels[$day] ?? ('Day ' . $day),
                'selected' => $selectedday === (string)$day,
            ];
        }

        return $options;
    }

    /**
     * Apply teacher/class filters locally for admin views.
     *
     * @param array $timetable
     * @param string $teacherid
     * @param string $classid
     * @return array
     */
    private function apply_admin_filters(array $timetable, string $teacherid, string $classid): array {
        if ($teacherid === '' && $classid === '') {
            return $timetable;
        }

        $timetable['entries'] = array_values(array_filter($timetable['entries'] ?? [], function(array $entry) use ($teacherid, $classid): bool {
            $teachermatch = $teacherid === '' || (string)($entry['teachers']['id'] ?? '') === $teacherid;
            $classmatch = $classid === '' || (string)($entry['classes']['id'] ?? '') === $classid;
            return $teachermatch && $classmatch;
        }));

        return $timetable;
    }

    /**
     * Blend a hex color toward white for softer PDF fills.
     *
     * @param string $hex
     * @param float $weight
     * @return string
     */
    private function fade_hex_color(string $hex, float $weight): string {
        $hex = ltrim(trim($hex), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '#f8fafc';
        }

        $weight = max(0.0, min(1.0, $weight));
        $channels = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        $blended = array_map(function(int $channel) use ($weight): int {
            return (int)round(($channel * (1 - $weight)) + (255 * $weight));
        }, $channels);

        return sprintf('#%02x%02x%02x', $blended[0], $blended[1], $blended[2]);
    }

    /**
     * Resolve an entry's external course identifier to a Moodle course URL.
     *
     * @param string $externalcourseid
     * @param string $mode
     * @param array $cache
     * @return array
     */
    private function resolve_course_link(string $externalcourseid, string $mode, array &$cache): array {
        global $DB;

        $externalcourseid = trim($externalcourseid);
        if ($externalcourseid === '') {
            return [];
        }

        $cachekey = $mode . ':' . $externalcourseid;
        if (array_key_exists($cachekey, $cache)) {
            return $cache[$cachekey];
        }

        $course = null;
        if ($mode === 'id' && ctype_digit($externalcourseid)) {
            $course = $DB->get_record('course', ['id' => (int)$externalcourseid], 'id,fullname,shortname', IGNORE_MISSING);
        } else if ($mode === 'idnumber') {
            $course = $DB->get_record('course', ['idnumber' => $externalcourseid], 'id,fullname,shortname', IGNORE_MISSING);
        }

        if (!$course) {
            $cache[$cachekey] = [];
            return [];
        }

        $label = trim((string)($course->shortname ?? ''));
        if ($label === '') {
            $label = trim((string)($course->fullname ?? ''));
        }
        if ($label === '') {
            $label = get_string('course', 'local_timetable');
        }

        $cache[$cachekey] = [
            'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'label' => $label,
        ];
        return $cache[$cachekey];
    }

    /**
     * Normalize a user argument into a Moodle user record.
     *
     * @param mixed $user
     * @return \stdClass
     */
    private function resolve_user_record($user): \stdClass {
        global $DB, $USER;

        if ($user instanceof \stdClass && !empty($user->id)) {
            return $user;
        }

        if (is_numeric($user)) {
            $record = \core_user::get_user((int)$user);
            if ($record instanceof \stdClass && !empty($record->id)) {
                return $record;
            }
        }

        if (is_string($user) && $user !== '') {
            if ($USER instanceof \stdClass && !empty($USER->id) && ((string)$USER->id === $user || (string)($USER->username ?? '') === $user)) {
                return $USER;
            }

            $record = $DB->get_record('user', ['username' => $user], '*', IGNORE_MISSING);
            if ($record instanceof \stdClass && !empty($record->id)) {
                return $record;
            }
        }

        throw new \coding_exception('Unable to resolve timetable user record.');
    }
}
