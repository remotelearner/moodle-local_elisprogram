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
 * @package    eliswidget_enrolment
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_enrolment\datatable;

/**
 * A datatable implementation for lists of programs - top-level.
 */
class program extends base {
    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_pgm';

    /** @var int The number of results displayed per page of the table. */
    const RESULTSPERPAGE = 5;

    /** @var int The ID of the course we're getting classes for. */
    protected $courseid = null;

    /** @var int The ID of the program we're getting classes for. Optional. */
    protected $programid = null;

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

        $langidnumber = get_string('curriculum_idnumber', 'local_elisprogram');
        $langname = get_string('curriculum_name', 'local_elisprogram');
        $langdescription = get_string('description', 'local_elisprogram');
        $langreqcredits = get_string('curriculum_reqcredits', 'local_elisprogram');

        $filters = [
                new \deepsight_filter_textsearch($DB, 'idnumber', $langidnumber, ['element.idnumber' => $langidnumber]),
                new \deepsight_filter_textsearch($DB, 'name', $langname, ['element.name' => $langname]),
                new \deepsight_filter_textsearch($DB, 'description', $langdescription, ['element.description' => $langdescription]),
                new \deepsight_filter_textsearch($DB, 'reqcredits', $langreqcredits, ['element.reqcredits' => $langreqcredits])
        ];

        // Add custom fields.
        $pgmctxlevel = \local_eliscore\context\helper::get_level_from_name('curriculum');
        $customfieldfilters = $this->get_custom_field_info($pgmctxlevel, ['table' => get_called_class()]);
        $filters = array_merge($filters, $customfieldfilters);

        // Restrict to configured enabled fields.
        $enabledfields = get_config('eliswidget_enrolment', 'curriculumenabledfields');
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
     * Get an array of fields to select in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of fields to select.
     */
    public function get_select_fields(array $filters = array()) {
        $selectfields = parent::get_select_fields($filters);
        $selectfields[] = 'element.reqcredits AS reqcredits';
        $selectfields[] = 'element.idnumber AS idnumber';
        $selectfields[] = 'element.name AS name';
        $selectfields[] = 'element.description AS description';
        return $selectfields;
    }

    /**
     * Get an array of datafields that will always be visible.
     *
     * @return array Array of filter aliases for fields that will always be visible.
     */
    public function get_fixed_visible_datafields() {
        return [];
    }

    /**
     * Get an array containing a list of visible and hidden datafields.
     *
     * For fields that are not fixed (see self::get_fixed_visible_datafields), additional fields are displayed when the user
     * searches on them. For fields that are not being searched on, they can be viewed by clicking a "more" link.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of field information, first item is visible fields, second is hidden fields.
     */
    public function get_datafields_by_visibility(array $filters = array()) {
       list($visible, $hidden) = parent::get_datafields_by_visibility($filters);
       $hidden['element_idnumber'] = get_string('program_idnumber', 'eliswidget_enrolment');
       $hidden['element_name'] = get_string('program_name', 'eliswidget_enrolment');
       $hidden['element_description'] = get_string('program_description', 'eliswidget_enrolment');
       $hidden['element_reqcredits'] = get_string('program_reqcredits', 'eliswidget_enrolment');
       return [$visible, $hidden];
    }

    /**
     * Converts an array of requested filter data into an SQL WHERE clause.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters = array()) {
        return parent::get_filter_sql($filters);
    }

    /**
     * Get a list of desired table joins to be used in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array with members: First item is an array of JOIN sql fragments, second is an array of parameters used by
     *               the JOIN sql fragments.
     */
    protected function get_join_sql(array $filters = array()) {
        global $CFG; // required by elispm::lib('data/curriculumstudent.class.php');
        require_once(\elispm::lib('data/curriculumstudent.class.php'));
        list($sql, $params) = parent::get_join_sql($filters);

        // Custom field joins.
        $enabledcfields = array_intersect_key($this->customfields, $this->availablefilters);
        $ctxlevel = \local_eliscore\context\helper::get_level_from_name('curriculum');
        // Get current user id.
        $euserid = \user::get_current_userid();

        $newsql = $this->get_custom_field_joins($ctxlevel, $enabledcfields);
        $newparams = [$euserid];
        $newsql[] = 'JOIN {'.\curriculumstudent::TABLE.'} curstu ON curstu.curriculumid = element.id AND curstu.userid = ?';
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
     * Get search results/
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @param int $page The page being displayed.
     * @return \moodle_recordset A recordset of program information.
     */
    public function get_search_results(array $filters = array(), $page = 1) {
        global $CFG, $DB;
        require_once(\elispm::lib('data/programcrsset.class.php'));
        // Get current user id.
        $euserid = \user::get_current_userid();
        list($pageresults, $totalresultsamt) = parent::get_search_results($filters, $page);
        $pageresultsar = [];
        foreach ($pageresults as $id => $result) {
            $result->header = get_string('program_header', 'eliswidget_enrolment', $result);
            $result->numcrssets = \programcrsset::count(new \field_filter('prgid', $id), $DB);
            if ($result->reqcredits > 0 && ($pgmstu = new \curriculumstudent(['curriculumid' => $id, 'userid' => $euserid]))) {
                $pgmstu->load();
                $result->pctcomplete = $pgmstu->get_percent_complete();
            } else {
                $result->pctcomplete = -1; // N/A.
            }
            $pageresultsar[$id] = $result;
        }

        // If idnumber/name filters match 'Non[ -]Program' or last page of listing then add Non-Program section
        $lastpage = 1 + (int)($totalresultsamt / static::RESULTSPERPAGE) == $page;
        $nonprogram = false;
        if ((isset($filters['idnumber']) && isset($filters['idnumber'][0]) && stripos('non-program', $filters['idnumber'][0]) !== false) ||
                (isset($filters['name']) && isset($filters['name'][0]) && stripos('non-program', $filters['name'][0]) !== false)) {
            $nonprogram = true;
        }
        if (!$nonprogram && ((!isset($filters['idnumber']) && !isset($filters['name'])) || (isset($filters['idnumber']) && empty($filters['idnumber'][0])) ||
                (isset($filters['name']) && empty($filters['name'][0]))) && $lastpage) {
            $nonprogram = true;
        }
        if ($nonprogram) {
            $noncursql = 'SELECT COUNT(\'x\') FROM {'.\student::TABLE.'} stu
                                              JOIN {'.\pmclass::TABLE.'} cls ON stu.classid = cls.id
                                         LEFT JOIN ({'.\curriculumcourse::TABLE.'} curcrs
                                                    JOIN {'.\curriculumstudent::TABLE.'} curstu ON curcrs.curriculumid = curstu.curriculumid)
                                                   ON cls.courseid = curcrs.courseid
                                                   AND stu.userid = curstu.userid
                                         LEFT JOIN ({'.\crssetcourse::TABLE.'} csc
                                                    JOIN {'.\programcrsset::TABLE.'} pcs ON pcs.crssetid = csc.crssetid
                                                    JOIN {'.\curriculumstudent::TABLE.'} curstu2 ON pcs.prgid = curstu2.curriculumid)
                                                   ON csc.courseid = cls.courseid
                                                   AND stu.userid = curstu2.userid
                                             WHERE curstu.id IS NULL
                                                   AND curstu2.id IS NULL
                                                   AND curcrs.id IS NULL
                                                   AND pcs.id IS NULL
                                                   AND stu.userid = ?';
            if ($DB->count_records_sql($noncursql, [$euserid])) {
                $nonprogramobj = new \stdClass;
                $nonprogramobj->id = 'none';
                $nonprogramobj->element_id = 'none';
                $nonprogramobj->element_name = get_string('nonprogramcourses', 'eliswidget_enrolment');
                $nonprogramobj->header = get_string('nonprogramcourses', 'eliswidget_enrolment');
                $nonprogramobj->numcrssets = 0;
                $nonprogramobj->pctcomplete = -1; // N/A.
                $pageresultsar['none'] = $nonprogramobj;
            }
        }
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
