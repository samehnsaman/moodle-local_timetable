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
 * English strings for local_timetable.
 *
 * @package   local_timetable
 * @copyright 2026 Sameh Naim <sameh@timetable.digital>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Timetable Digital';
$string['privacy:metadata'] = 'The Timetable Digital plugin stores cached timetable responses for faster page loads.';
$string['privacy:metadata:local_timetable_cache'] = 'Cached timetable responses for individual users are stored to reduce repeated API calls.';
$string['privacy:metadata:local_timetable_cache:userid'] = 'The id of the Moodle user whose timetable cache is stored.';
$string['privacy:metadata:local_timetable_cache:useremail'] = 'The email address used to request teacher timetable data.';
$string['privacy:metadata:local_timetable_cache:cohortkeys'] = 'The cohort idnumbers used to request class timetable data.';
$string['privacy:metadata:local_timetable_cache:responsejson'] = 'The raw timetable response cached for the user.';
$string['privacy:metadata:local_timetable_cache:versionsjson'] = 'The timetable version metadata cached for freshness checks.';
$string['privacy:metadata:local_timetable_cache:timefetched'] = 'The time the timetable data was last fetched from timetable.digital.';
$string['privacy:metadata:local_timetable_cache:timemodified'] = 'The time the cached timetable row was last updated in Moodle.';
$string['privacy:metadata:timetable_digital'] = 'In order to display personal timetable data, the plugin sends selected user data to timetable.digital.';
$string['privacy:metadata:timetable_digital:email'] = 'The user email address is sent to retrieve teacher timetable entries.';
$string['privacy:metadata:timetable_digital:class_external_id'] = 'The user cohort idnumbers are sent to retrieve class timetable entries.';

$string['timetable:view'] = 'View personal Timetable Digital timetable';
$string['timetable:manage'] = 'Manage Timetable Digital settings';

$string['settings:setupinfo'] = 'Setup';
$string['settings:setupinfo_desc'] = 'You need an API key from <a href="https://timetable.digital" target="_blank" rel="noopener">timetable.digital</a> before this plugin can connect to your timetable data.';
$string['settings:apiurl'] = 'Timetable Digital API URL';
$string['settings:apiurl_desc'] = 'Base URL of the Timetable Digital public API.';
$string['settings:apikey'] = 'API key';
$string['settings:apikey_desc'] = 'School API key used to access the Timetable Digital public API.';
$string['settings:origin'] = 'Origin header';
$string['settings:origin_desc'] = 'Optional Origin header to send with API requests. Leave empty to use Moodle site URL automatically.';
$string['settings:timeout'] = 'Request timeout';
$string['settings:timeout_desc'] = 'Maximum number of seconds to wait for the timetable API.';
$string['settings:courseidentifiermode'] = 'Course identifier mode';
$string['settings:courseidentifiermode_desc'] = 'Choose whether external_course_id should be matched against Moodle course ID or course idnumber.';
$string['settings:courseidentifiermode_id'] = 'Course ID';
$string['settings:courseidentifiermode_idnumber'] = 'Course idnumber';
$string['settings:debugenabled'] = 'Enable debug output';
$string['settings:debugenabled_desc'] = 'Show request and response debug information on the timetable page.';

$string['mytimetable'] = 'My timetable';
$string['mytimetableprofile'] = 'My timetable.digital';
$string['filterteacher'] = 'Teacher';
$string['filterclass'] = 'Class';
$string['filterday'] = 'Day';
$string['filterallteachers'] = 'All teachers';
$string['filterallclasses'] = 'All classes';
$string['filteralldays'] = 'All days';
$string['applyfilters'] = 'Apply';
$string['clearfilters'] = 'Clear';
$string['adminview'] = 'Admin view';
$string['refresh'] = 'Update';
$string['refreshshort'] = 'Reload';
$string['exportpdf'] = 'Print';
$string['exportpdf_full'] = 'Print / PDF';
$string['joinmeeting'] = 'Join';
$string['course'] = 'Course';
$string['onlinesession'] = 'Online session';
$string['entrydetails'] = 'Entry details';
$string['pdfgenerated'] = 'Generated';
$string['poweredby'] = 'Powered by timetable.digital';
$string['poweredbyurl'] = 'https://timetable.digital';
$string['refreshedusingcache'] = 'Timetable Digital could not be reached, so the last cached timetable is being shown.';
$string['nocacheavailable'] = 'Timetable Digital could not be reached and no cached timetable is available.';
$string['nofiltersavailable'] = 'No email or cohort ID numbers were found for your account, so no timetable query can be built.';
$string['apinotconfigured'] = 'Timetable Digital is not configured yet. Please ask a site administrator to set the API URL and API key.';
$string['apierror'] = 'Timetable Digital API error: {$a}';
$string['invalidresponse'] = 'Timetable Digital returned an invalid response.';
$string['notimetables'] = 'No timetable data was returned for your account.';
$string['requestdebug'] = 'Request debug';
$string['requesturl'] = 'URL';
$string['requestbaseurl'] = 'Base URL';
$string['requestparams'] = 'Query params';
$string['requestemail'] = 'Email';
$string['requestcohorts'] = 'Cohort idnumbers';
$string['requestfilters'] = 'Source filters';
$string['requeststatus'] = 'HTTP status';
$string['requestresponse'] = 'Server response';
$string['requestcurl'] = 'cURL repro';
$string['requestnone'] = '(none)';
$string['requestrepro'] = 'Postman: send a GET request to the URL above with the same query params and your API key in the x-api-key header.';
$string['termlabel'] = 'Term';
$string['break'] = 'Break';
$string['staleindicator'] = 'Cached copy';
$string['filtersummary'] = 'Source filters: {$a}';
$string['pdffilename'] = 'timetable-digital';
