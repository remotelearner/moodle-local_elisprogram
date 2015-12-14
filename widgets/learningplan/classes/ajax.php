<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    eliswidget_learningplan
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_learningplan;

/**
 * Dashboard AJAX response class.
 *
 * This receives AJAX requests, processes the request (get or change information), and sends the response.
 */
class ajax {

    /** @var string The URL that receives requests. */
    protected $endpoint;

    /**
     * Constructor.
     *
     * @param string $endpoint The URL that receives request.
     */
    public function __construct($endpoint) {
        $this->endpoint = $endpoint;
    }

    /**
     * Respond to an ajax request.
     *
     * @param string $requestmethod The request method - "GET" or "POST".
     * @param string $mode The requested mode.
     * @param array $data An array of received data.
     * @return string Response JSON.
     */
    public function respond($requestmethod, $mode, $data) {
        try {
            // Validate $requestmethod.
            if (!is_string($requestmethod)) {
                throw new \coding_exception('Bad request method passed.');
            }
            $requestmethod = strtolower($requestmethod);
            if (in_array($requestmethod, ['get', 'post'], true) !== true) {
                throw new \coding_exception('Request method must be get or post.');
            }

            // Validate $mode.
            if (!is_string($mode)) {
                throw new \coding_exception('Bad mode received.');
            }

            $method = $requestmethod.'_'.$mode;
            if (method_exists($this, $method)) {
                $result = $this->$method($data);
                return $this->makeresponse('success', $result);
            } else {
                throw new \coding_exception('Mode not found.');
            }
        } catch (\Exception $e) {
            return $this->makeresponse('fail', [], $e->getMessage());
        }
    }

    /**
     * Make a JSON response from received data.
     *
     * @param string $status Response status - "success" or "fail".
     * @param array $data Response data.
     * @param string $msg A human-readable message to go along with the response. Optional.
     * @return string JSON-encoded response.
     */
    protected function makeresponse($status, $data = array(), $msg = '') {
        if ($data instanceof \moodle_recordset) {
            $data = $this->results2array($data);
        }
        return json_encode(['status' => $status, 'msg' => $msg, 'data' => $data]);
    }

    /**
     * Takes either an array or recordset as input and ensures array output. Used for preparing search results for json encoding.
     *
     * @param array|\moodle_recordset $data Search results in array or recordset form.
     * @return array Search results in array form.
     */
    protected function results2array($data) {
        if (is_array($data)) {
            return array_values($data);
        } else if ($data instanceof \moodle_recordset) {
            $dataar = [];
            foreach ($data as $id => $record) {
                $dataar[] = $record;
            }
            return $dataar;
        } else {
            throw new \coding_exception('results2array recieved something that wasn\'t an array or a recordset');
        }
    }

    /**
     * Convert an array of filter objects into a more lightweight array that can be json encoded.
     *
     * @param array $filterobjs An array of \deepsight_filter objects.
     * @return array An array of filter information and javascript options, indexed by filter name.
     */
    protected function preparefilters(array $filterobjs = array()) {
        $filters = [];
        foreach ($filterobjs as $i => $filter) {
            if ($filter instanceof \deepsight_filter) {
                $filters[$filter->get_name()] = [
                    'type' => $filter::TYPE,
                    'opts' => $filter->get_js_opts()
                ];
            }
        }
        return $filters;
    }

    /**
     * Respond to a request aimed at a specific filter.
     *
     * @param array $data Incoming data.
     */
    protected function get_filter(array $data) {
        global $DB;
        $table = optional_param('table', '', PARAM_TEXT);
        $filtername = optional_param('filtername', '', PARAM_TEXT);
        if (empty($table) || empty($filtername)) {
            throw new \Exception('No table or filter received.');
        }
        $table = '\\'.$table;
        if ((strpos($table, '\eliswidget_learningplan\datatable\\') !== 0 && strpos($table, '\eliswidget_enrolment\datatable\\') !== 0) || !class_exists($table)) { // TBD?
            throw new \Exception('Invalid table name received.');
        }
        $datatable = new $table($DB, $this->endpoint);
        $filter = $datatable->get_filter($filtername);
        if (!empty($filter)) {
            echo $filter->respond_to_js();
            die();
        } else {
            throw new \Exception('No filter found');
        }
    }

    /**
     * Get a list of courses present in a program.
     *
     * @param array $data Array containing "programid", "filters", and "page".
     * @return array Array of response information containing information on filters to render, search results, and other details
     *               needed by the front-end.
     */
    public function get_classesforprogram(array $data) {
        global $DB;
        $response = [];
        if (isset($data['programid'])) {
            require_once(\elispm::lib('data/user.class.php'));
            $euserid = \user::get_current_userid();
            if (!empty($data['programid']) && is_numeric($data['programid'])) {
                $pgmclsdatatable = new \eliswidget_learningplan\datatable\programclasses($DB, $this->endpoint);
                $pgmclsdatatable->set_programid($data['programid']);
                $pgmclsdatatable->set_userid($euserid);
                $pgmclsresponse = $this->get_listing_response($pgmclsdatatable, $data);
                $crssetclsdatatable = new \eliswidget_learningplan\datatable\crssetclasses($DB, $this->endpoint);
                $crssetclsdatatable->set_programid($data['programid']);
                $crssetclsdatatable->set_userid($euserid);
                $crssetclsresponse = $this->get_listing_response($crssetclsdatatable, $data);
                if (!empty($pgmclsresponse['totalresults'])) {
                    $response = $pgmclsresponse;
                    if (!empty($crssetclsresponse['totalresults'])) {
                        $response['children'] = array_merge($pgmclsresponse['children'], $crssetclsresponse['children']);
                        $response['totalresults'] += $crssetclsresponse['totalresults'];
                    }
                } else if (!empty($crssetclsresponse['totalresults'])) {
                    $response = $crssetclsresponse;
                } else {
                    $response = $pgmclsresponse;
                }
            } else if ($data['programid'] == 'none') {
                $datatable = new \eliswidget_learningplan\datatable\nonprogramclasses($DB, $this->endpoint);
                $datatable->set_userid($euserid);
                $response = $this->get_listing_response($datatable, $data);
            } else {
                throw new \Exception('Invalid Program ID received.');
            }
        } else {
            throw new \Exception('No Program ID received.');
        }
        return $response;
    }

    /**
     * Get a list of programs for a user.
     *
     * @param array $data Array containing "filters" and "page".
     * @return array Array of response information containing information on filters to render, search results, and other details
     *               needed by the front-end.
     */
    public function get_programsforuser(array $data) {
        global $DB;
        $datatable = new \eliswidget_enrolment\datatable\program($DB, $this->endpoint); // TBD?
        $datatable->set_progressbar(get_config('eliswidget_learningplan', 'progressbarenabled'));
        return $this->get_listing_response($datatable, $data);
    }

    /**
     * Respond to a listing request for a given entity type.
     *
     * @param \eliswidget_enrolment\datatable\base $datatable The datatable that handles the list.
     * @param array $data Received data. Includes 'filters', and 'page' keys.
     * @return array Array of JSON-able response information.
     */
    protected function get_listing_response(\eliswidget_enrolment\datatable\base $datatable, $data) {
        if (!empty($data['filters'])) {
            $data['filters'] = @json_decode($data['filters'], true);
        }
        if (empty($data['filters']) || !is_array($data['filters'])) {
            $data['filters'] = [];
        }
        $allfilters = $this->preparefilters($datatable->get_filters());
        $initialfilters = $datatable->get_initial_filters(); // TBD?
        $page = (isset($data['page']) && is_numeric($data['page'])) ? (int)$data['page'] : 1;

        // Assemble response.
        list($pageresults, $totalresultsamt) = $datatable->get_search_results($data['filters'], $page);
        list($visibledatafields, $hiddendatafields) = $datatable->get_datafields_by_visibility($data['filters']);
        return [
            'filters' => $allfilters,
            'initialfilters' => $initialfilters,
            'fields' => ['visible' => $visibledatafields, 'hidden' => $hiddendatafields],
            'children' => $this->results2array($pageresults),
            'perpage' => 1000, // TBD - was: $datatable::RESULTSPERPAGE,
            'totalresults' => $totalresultsamt,
        ];
    }
}
