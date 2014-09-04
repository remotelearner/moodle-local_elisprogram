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
 * A datatable implementation for lists of classes within course.
 */
class pmclass extends base {
    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_cls';

    /** @var int The number of results displayed per page of the table. */
    const RESULTSPERPAGE = 5;

    /** @var int The ID of the course we're getting classes for. */
    protected $courseid = null;

    /**
     * Gets an array of available filters.
     *
     * @return array An array of \deepsight_filter objects that will be available.
     */
    public function get_filters() {
        global $DB, $CFG;

        require_once(\elispm::lib('deepsight/lib/filter.php'));
        require_once(\elispm::lib('deepsight/lib/filters/textsearch.filter.php'));
        require_once(\elispm::lib('deepsight/lib/filters/date.filter.php'));

        $langidnumber = get_string('class_idnumber', 'local_elisprogram');
        $langstartdate = get_string('class_startdate', 'local_elisprogram');
        $langenddate = get_string('class_enddate', 'local_elisprogram');

        $filters = [
                new \deepsight_filter_textsearch($DB, 'idnumber', $langidnumber, ['element.idnumber' => $langidnumber]),
                new \deepsight_filter_date($DB, 'startdate', $langstartdate, ['element.startdate' => $langstartdate]),
                new \deepsight_filter_date($DB, 'enddate', $langenddate, ['element.enddate' => $langenddate]),
        ];

        // Add custom fields.
        $pmclassctxlevel = \local_eliscore\context\helper::get_level_from_name('class');
        $customfieldfilters = $this->get_custom_field_info($pmclassctxlevel, ['table' => get_called_class()]);
        $filters = array_merge($filters, $customfieldfilters);

        // Restrict to configured enabled fields.
        $enabledfields = get_config('eliswidget_enrolment', 'classenabledfields');
        if (!empty($enabledfields)) {
            $enabledfields = explode(',', $enabledfields);
            foreach ($filters as $i => $filter) {
                if (!in_array($filter->get_name(), $enabledfields)) {
                    unset($filters[$i]);
                }
            }
        }

        return $filters;
    }

    /**
     * Gets an array of fields that will always be selected, regardless of what has been enabled.
     *
     * @return array An array of fields that will always be selected.
     */
    public function get_fixed_select_fields() {
        return [
            'element.idnumber' => '',
            'enrol.id' => '',
            'enrol.completestatusid' => '',
            'enrol.grade' => '',
            'waitlist.id' => '',
        ];
    }

    /**
     * Get an array of datafields that will always be visible.
     *
     * @return array Array of filter aliases for fields that will always be visible.
     */
    public function get_fixed_visible_datafields() {
        return ['idnumber'];
    }

    /**
     * Converts an array of requested filter data into an SQL WHERE clause.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters = array()) {
        $filters[] = ['sql' => 'element.courseid = ?', 'params' => [$this->courseid]];
        return parent::get_filter_sql($filters);
    }

    /**
     * Set the ID of the course we're getting classes for.
     *
     * @param int $courseid The ID of the course we're getting classes for.
     */
    public function set_courseid($courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Get a list of desired table joins to be used in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array with members: First item is an array of JOIN sql fragments, second is an array of parameters used by
     *               the JOIN sql fragments.
     */
    protected function get_join_sql(array $filters = array()) {
        require_once(\elispm::lib('data/user.class.php'));
        list($sql, $params) = parent::get_join_sql($filters);

        // Custom field joins.
        $enabledcfields = array_intersect_key($this->customfields, $this->availablefilters);
        $ctxlevel = \local_eliscore\context\helper::get_level_from_name('class');
        $newsql = $this->get_custom_field_joins($ctxlevel, $enabledcfields);

        // Enrolment and waitlist information.
        $euserid = \user::get_current_userid();
        $newsql[] = 'LEFT JOIN {local_elisprogram_cls_enrol} enrol ON enrol.classid = element.id AND enrol.userid = ?';
        $newsql[] = 'LEFT JOIN {local_elisprogram_waitlist} waitlist ON waitlist.classid = element.id AND waitlist.userid = ?';
        $newparams = [$euserid, $euserid];

        return [array_merge($sql, $newsql), array_merge($params, $newparams)];
    }

    /**
     * Get an ORDER BY sql fragment to be used in the get_searcH_results method.
     *
     * @return string An ORDER BY sql fragment, if desired.
     */
    protected function get_sort_sql() {
        return 'ORDER BY element.idnumber ASC';
    }

    /**
     * Get courses assigned to a program.
     *
     * @param int $programid The ID of the program to get courses for.
     * @return \moodle_recordset A recordset of course information.
     */
    public function get_search_results(array $filters = array(), $page = 1) {
        global $DB;
        list($pageresults, $totalresultsamt) = parent::get_search_results($filters, $page);

        if ($totalresultsamt <= 0) {
            return [$pageresults, $totalresultsamt];
        }

        // Assemble class ids.
        // Note: get_search results returns a recordset, so we also array-ify pageresults - recordsets are one-time-use.
        $classids = [];
        $pageresultsar = [];
        foreach ($pageresults as $id => $result) {
            $classids[] = $result->id;
            $pageresultsar[$id] = $result;
            $pageresultsar[$id]->instructors = [];
        }
        unset($pageresults);

        // Get instructor information for each class.
        list($clsidinsql, $clsidinparams) = $DB->get_in_or_equal($classids);
        $sql = 'SELECT ins.classid,
                       ins.userid,
                       usr.email,
                       usr.firstname,
                       usr.lastname
                  FROM {local_elisprogram_cls_nstrct} ins
                  JOIN {local_elisprogram_usr} usr ON ins.userid = usr.id
                 WHERE ins.classid '.$clsidinsql;
        $instructors = $DB->get_recordset_sql($sql, $clsidinparams);
        $instructorsbyclass = [];
        foreach ($instructors as $i => $instructor) {
            if (isset($pageresultsar[$instructor->classid])) {
                $pageresultsar[$instructor->classid]->instructors[] = $instructor;
            }
        }

        // Return the results. Note: array_values is used to convert $pageresultsar into a numeric array, which jsonencode will
        // encode to a js array. An associative array will be converted into a js object which will cause problems on the front
        // end.
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
