<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2016 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    eliswidget_teachingplan
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_teachingplan\datatable;

if (!defined('TESTING')) {
    define('TESTING' , 1);
}

/**
 * A datatable implementation for lists of courses.
 */
class courses extends \eliswidget_common\datatable\base {
    /** @var int The number of results displayed per page of the table. */
    const RESULTSPERPAGE = 10;

    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_crs';

    /** @var int The ID of the instructor we're getting courses for. */
    protected $userid = null;

    /** @var bool The state of the course progressbar. */
    protected $progressbarenabled = true;

    /**
     * Set the ID of the instructor we're getting courses for.
     *
     * @param int $userid The ID of the instructor we're getting courses for.
     */
    public function set_userid($userid) {
        $this->userid = $userid;
    }

    /**
     * Set the progressbar enabled.
     *
     * @param bool $progressbarenabled The state of the progress bar display.
     */
    public function set_progressbar($progressbarenabled) {
        $this->progressbarenabled = $progressbarenabled;
    }

    /**
     * Gets an array of available filters.
     *
     * @return array An array of \deepsight_filter objects that will be available.
     */
    public function get_filters() {
        return [];
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
        $selectfields[] = 'element.completion_grade AS completiongrade';
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
        return [];
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
        if (defined('TESTING')) {
            $courses = [];
            $tot = ($page == 1) ? 10 : 12;
            for ($i = ($page == 1) ? 0 : 10; $i < $tot; ++$i) {
                $crs = new \stdClass;
                $crs->element_id = $i + 2;
                $crs->header = 'CD-'.$crs->element_id;
                $crs->name = 'CD-'.$crs->element_id.' CourseName';
                $crs->idnumber = 'CD-'.$crs->element_id;
                $crs->programs = "PRG-{$i}*, PRG-1{$i} (CourseSets: CRSSET-{$crs->element_id})";
                $crs->description = 'Some random description for Course CD-'.$i;
                $crs->credits = '1.24';
                $crs->completiongrade = '55.6789';
                $crs->pctcomplete = empty($this->progressbarenabled) ? -1 : $i * 100.0 / 12.0;
                $courses[] = $crs;
            }
            return [$courses, 12];
        }
        list($pageresults, $totalresultsamt) = parent::get_search_results($filters, $page);
        $pageresultsar = [];
        foreach ($pageresults as $id => $result) {
            $result->header = get_string('course_header', 'eliswidget_teachingplan', $result);
            $pageresultsar[$id] = $result;
        }
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
