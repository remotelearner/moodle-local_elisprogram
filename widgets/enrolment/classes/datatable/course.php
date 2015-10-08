<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    eliswidget_enrolment
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

namespace eliswidget_enrolment\datatable;

/**
 * A datatable implementation for lists of courses.
 */
class course extends base {
    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_crs';

    /** @var int The number of results displayed per page of the table. */
    const RESULTSPERPAGE = 10;

    /**
     * Gets an array of available filters.
     *
     * @return array An array of \deepsight_filter objects that will be available.
     */
    public function get_filters() {
        global $CFG;

        // If availablefilters has been populated, we can just use that.
        if (!empty($this->availablefilters)) {
            return $this->availablefilters;
        }

        require_once(\elispm::lib('deepsight/lib/filter.php'));
        require_once(\elispm::lib('data/user.class.php'));

        // Basic fields.
        $langname = get_string('course_name', 'local_elisprogram');
        $langcode = get_string('course_code', 'local_elisprogram');
        $langidnumber = get_string('course_idnumber', 'local_elisprogram');
        $langdesc = get_string('course_syllabus', 'local_elisprogram');
        $langcredits = get_string('credits', 'local_elisprogram');
        $langcost = get_string('cost', 'local_elisprogram');
        $langversion = get_string('course_version', 'local_elisprogram');
        $langcoursestatus = get_string('course_status', 'local_elisprogram');
        $euserid = \user::get_current_userid();
        $enrolalias = (get_class($this) == 'eliswidget_enrolment\\datatable\\nonprogramcourse') ? 'stu' : 'enrol';
        $coursestatusfilter = new \deepsight_filter_coursestatus($this->DB, 'coursestatus', $langcoursestatus, [
           "{$enrolalias}.userid" => $euserid,
           'tablealias' => $enrolalias], $CFG->wwwroot.'/local/elisprogram/widgets/enrolment/ajax.php');
        $coursestatusfilter->set_default('');
        $filters = [
                new \deepsight_filter_textsearch($this->DB, 'name', $langname, ['element.name' => $langname]),
                new \deepsight_filter_textsearch($this->DB, 'code', $langcode, ['element.code' => $langcode]),
                new \deepsight_filter_textsearch($this->DB, 'idnumber', $langidnumber, ['element.idnumber' => $langidnumber]),
                new \deepsight_filter_textsearch($this->DB, 'description', $langdesc, ['element.syllabus' => $langdesc]),
                new \deepsight_filter_textsearch($this->DB, 'credits', $langcredits, ['element.credits' => $langcredits]),
                new \deepsight_filter_textsearch($this->DB, 'cost', $langcost, ['element.cost' => $langcost]),
                new \deepsight_filter_textsearch($this->DB, 'version', $langversion, ['element.version' => $langversion]),
                $coursestatusfilter
        ];

        // Add custom fields.
        $coursectxlevel = \local_eliscore\context\helper::get_level_from_name('course');
        $customfieldfilters = $this->get_custom_field_info($coursectxlevel, ['table' => get_called_class()]);
        $filters = array_merge($filters, $customfieldfilters);

        // Restrict to visible fields.
        foreach ($filters as $i => $filter) {
            $filtername = $filter->get_name();
            $enabled = get_config('eliswidget_enrolment', 'course_field_'.$filtername.'_radio');
            if ($enabled == 1 || ($enabled === false && strpos($filtername, 'cf_') === 0)) { // Hidden.
                unset($filters[$i]);
            } else if ($enabled == 3) { // Locked.
                $this->lockedfilters[$filtername] = true;
            }
        }

        return $filters;
    }

    /**
     * Get an array of fields to select in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of fields to select.
     */
    protected function get_select_fields(array $filters = array()) {
        $selectfields = parent::get_select_fields($filters);
        $selectfields[] = 'element.idnumber AS idnumber';
        $selectfields[] = 'element.name AS name';
        $selectfields[] = 'element.code AS code';
        $selectfields[] = 'element.syllabus AS syllabus';
        $selectfields[] = 'element.lengthdescription AS description';
        $selectfields[] = 'element.length AS length';
        $selectfields[] = 'element.credits AS credits';
        $selectfields[] = 'element.completion_grade AS completion_grade';
        $selectfields[] = 'element.cost AS cost';
        $selectfields[] = 'element.version AS version';
        return $selectfields;
    }

    /**
     * Gets an array of fields that will always be selected, regardless of what has been enabled.
     *
     * @return array An array of fields that will always be selected.
     */
    public function get_fixed_select_fields() {
        return ['element.idnumber' => '', 'element.name' => ''];
    }

    /**
     * Get an array of datafields that will always be visible.
     *
     * @return array Array of filter aliases for fields that will always be visible.
     */
    public function get_fixed_visible_datafields() {
        return ['idnumber', 'description'];
    }

    /**
     * Get a list of desired table joins to be used in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array with members: First item is an array of JOIN sql fragments, second is an array of parameters used by
     *               the JOIN sql fragments.
     */
    protected function get_join_sql(array $filters = array()) {
        list($sql, $params) = parent::get_join_sql($filters);

        $enabledcfields = array_intersect_key($this->customfields, $this->availablefilters);
        $ctxlevel = \local_eliscore\context\helper::get_level_from_name('course');
        $newsql = $this->get_active_filters_custom_field_joins($filters, $ctxlevel, $enabledcfields);
        return [array_merge($sql, $newsql), $params];
    }

    /**
     * Get search results/
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @param int $page The page being displayed.
     * @return array An array of course information.
     */
    public function get_search_results(array $filters = array(), $page = 1) {
        list($pageresults, $totalresultsamt) = parent::get_search_results($filters, $page);
        $pageresultsar = [];
        foreach ($pageresults as $id => $result) {
            $result->header = get_string('course_header', 'eliswidget_enrolment', $result);
            $pageresultsar[$id] = $result;
        }
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
