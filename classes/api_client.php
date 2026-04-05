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
 * API client for timetable.digital.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_timetable;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Thin client around the Timetable Digital public timetable endpoint.
 */
class api_client {
    /** @var string */
    private $baseurl;
    /** @var string */
    private $apikey;
    /** @var int */
    private $timeout;
    /** @var string */
    private $origin;
    /** @var string */
    private $lasturl = '';
    /** @var int */
    private $laststatuscode = 0;
    /** @var string */
    private $lastresponsebody = '';

    public function __construct() {
        global $CFG;

        $this->baseurl = trim((string)get_config('local_timetable', 'api_url'));
        $this->apikey = trim((string)get_config('local_timetable', 'api_key'));
        $this->timeout = (int)get_config('local_timetable', 'request_timeout');
        $this->origin = trim((string)get_config('local_timetable', 'origin'));
        if ($this->timeout <= 0) {
            $this->timeout = 20;
        }
        if ($this->origin === '' && !empty($CFG->wwwroot)) {
            $this->origin = rtrim((string)$CFG->wwwroot, '/');
        }
    }

    /**
     * Fetch timetable response from Timetable Digital.
     *
     * @param array $params
     * @return array
     * @throws \moodle_exception
     */
    public function get_timetable(array $params): array {
        if ($this->baseurl === '' || $this->apikey === '') {
            throw new \moodle_exception('apinotconfigured', 'local_timetable');
        }

        $url = rtrim($this->baseurl, '/') . '/timetable';
        $this->lasturl = $url;
        $this->laststatuscode = 0;
        $this->lastresponsebody = '';

        $curl = new \curl();
        $headers = [
            'x-api-key: ' . $this->apikey,
            'Accept: application/json',
        ];
        if ($this->origin !== '') {
            $headers[] = 'Origin: ' . $this->origin;
        }
        $curl->setHeader($headers);
        $options = [
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_RETURNTRANSFER' => true,
        ];

        $response = $curl->get($url, $params, $options);
        $statuscode = $curl->get_info()['http_code'] ?? 0;
        $this->laststatuscode = (int)$statuscode;
        $this->lastresponsebody = is_string($response) ? $response : '';
        $this->lasturl = $this->build_timetable_url($params);

        if ((int)$statuscode !== 200 || $response === false || $response === '') {
            $message = $this->extract_error_message($response);
            throw new \moodle_exception('apierror', 'local_timetable', '', $message);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !array_key_exists('timetables', $decoded)) {
            throw new \moodle_exception('invalidresponse', 'local_timetable');
        }

        return $decoded;
    }

    /**
     * Build the request URL for debugging or reproduction.
     *
     * @param array $params
     * @return string
     */
    public function build_timetable_url(array $params): string {
        $url = rtrim($this->baseurl, '/') . '/timetable';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    /**
     * Return the base timetable endpoint without query params.
     *
     * @return string
     */
    public function get_timetable_endpoint(): string {
        return rtrim($this->baseurl, '/') . '/timetable';
    }

    /**
     * Return the last HTTP debug info captured by this client.
     *
     * @return array
     */
    public function get_last_debug(): array {
        return [
            'url' => $this->lasturl,
            'status' => $this->laststatuscode,
            'response' => $this->lastresponsebody,
        ];
    }

    /**
     * Best-effort extraction of provider errors.
     *
     * @param mixed $response
     * @return string
     */
    private function extract_error_message($response): string {
        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            if (is_array($decoded) && !empty($decoded['error'])) {
                return (string)$decoded['error'];
            }
        }

        return 'HTTP request failed';
    }
}
