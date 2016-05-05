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

/**
 * A datatable implementation for lists of classes.
 */
class classes extends \eliswidget_common\datatable\base {
    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_cls';

    /** @var int The ID of the instructor we're getting classes for. */
    protected $userid = null;

    /** @var int The ID of the course we're getting classes for. */
    protected $courseid = null;

    /** @var int The number of results displayed per page of the table. */
    const RESULTSPERPAGE = 1000;

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
        $selectfields[] = 'element.startdate AS startdate';
        $selectfields[] = 'element.enddate AS enddate';
        $selectfields[] = 'element.starttimehour AS starttimehour';
        $selectfields[] = 'element.starttimeminute AS starttimeminute';
        $selectfields[] = 'element.endtimehour AS endtimehour';
        $selectfields[] = 'element.endtimeminute AS endtimeminute';
        $selectfields[] = 'element.maxstudents AS maxstudents';
        $selectfields[] = 'mdlcrs.id AS moodlecourseid';
        $selectfields[] = 'mdlcrs.fullname AS moodlecoursefullname';
        $selectfields[] = 'mdlcrs.shortname AS moodlecourseshortname';
        $selectfields[] = 'mdlcrs.idnumber AS moodlecourseidnumber';
        $selectfields[] = 'mdlcrs.summary AS moodlecoursesummary';
        $selectfields[] = 'mdlcrs.startdate AS moodletime';
        $selectfields[] = 'COUNT(DISTINCT stu.id) AS numenrol';
        $selectfields[] = 'COUNT(DISTINCT stu2.id) AS inprogress';
        $selectfields[] = 'COUNT(DISTINCT waitlist.id) AS waiting';
        return $selectfields;
    }

    /**
     * Set the User ID of the instructor we're getting classes for.
     *
     * @param int $userid The User ID of the instructor we're getting classes for.
     */
    public function set_userid($userid) {
        $this->userid = $userid;
    }

    /**
     * Set the Course ID we're getting classes for.
     *
     * @param int $courseid The Course ID we're getting classes for.
     */
    public function set_courseid($courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Static method to get an array of table columns that will always be visible.
     *
     * @return array Array of filter aliases for fields that will always be visible.
     */
    public static function get_visible_tablecolumns() {
        $visiblecolumns = ['class_header', 'moodletime', 'startdate', 'enddate', 'classtime', 'maxstudents', 'enrolled', 'instructors'];
        foreach ($visiblecolumns as $key => $val) {
            $columnenabled = get_config('eliswidget_teachingplan', $val);
            if ($columnenabled !== false && $columnenabled != 1) {
                unset($visiblecolumns[$key]);
            }
        }
        return $visiblecolumns;
    }

    /**
     * Get an array of datafields that will always be visible.
     *
     * @return array Array of filter aliases for fields that will always be visible.
     */
    public function get_fixed_visible_datafields() {
        return static::get_visible_tablecolumns();
    }

    /**
     * Get a list of desired table joins to be used in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array with members: First item is an array of JOIN sql fragments, second is an array of parameters used by
     *               the JOIN sql fragments.
     */
    protected function get_join_sql(array $filters = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/instructor.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/classmoodlecourse.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/student.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/waitlist.class.php');
        list($sql, $params) = parent::get_join_sql($filters);
        $newsql = [
                'JOIN {'.\instructor::TABLE.'} ins ON ins.classid = element.id
                      AND ins.userid = ?',
                'LEFT JOIN {'.\classmoodlecourse::TABLE.'} clsmdl ON clsmdl.classid = element.id',
                'LEFT JOIN {course} mdlcrs ON mdlcrs.id = clsmdl.moodlecourseid',
                'LEFT JOIN {'.\student::TABLE.'} stu ON stu.classid = element.id',
                'LEFT JOIN {'.\student::TABLE.'} stu2 ON stu2.classid = element.id
                           AND stu2.completestatusid = '.STUSTATUS_NOTCOMPLETE,
                'LEFT JOIN {'.\waitlist::TABLE.'} waitlist ON waitlist.classid = element.id'
        ];
        return [array_merge($sql, $newsql), array_merge($params, [$this->userid])];
    }

    /**
     * Get a GROUP BY sql fragment to be used in the get_search_results method.
     *
     * @return string A GROUP BY sql fragment, if desired.
     */
    protected function get_groupby_sql() {
        return 'GROUP BY element.id';
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
     * Get an ORDER BY sql fragment to be used in the get_search_results method.
     *
     * @return string An ORDER BY sql fragment, if desired.
     */
    protected function get_sort_sql() {
        return 'ORDER BY element.idnumber ASC';
    }

    /**
     * Get instructors for class.
     *
     * @param int $classid The classid to get instrictors.
     * @return string  The list of instructors.
     */
    public static function get_instructors($classid) {
        global $CFG;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/instructor.class.php');
        $ret = '';
        $instructors = \instructor::find(new \field_filter('classid', $classid));
        foreach ($instructors as $instructor) {
            if (!empty($ret)) {
                $ret .= ', ';
            }
            $user = new \user($instructor->userid);
            $user->load();
            $ret .= $user->moodle_fullname();
        }
        return $ret;
    }

    /**
     * Get search results/
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @param int $page The page being displayed.
     * @return array An array of course information.
     */
    public function get_search_results(array $filters = array(), $page = 1) {
        global $CFG;
        list($pageresults, $totalresultsamt) = parent::get_search_results($filters, $page);
        $pageresultsar = [];
        $dateformat = get_string('date_format', 'eliswidget_teachingplan');
        foreach ($pageresults as $id => $result) {
            $result->wwwroot = $CFG->wwwroot;
            $result->conditional_moodlecourse = empty($result->moodlecourseid) ? '' : get_string('conditional_moodlecourse', 'eliswidget_teachingplan');
            $result->class_header = get_string('class_table_class_cell', 'eliswidget_teachingplan', $result);
            unset($result->wwwroot);
            unset($result->conditional_moodlecourse);
            if (empty($result->maxstudents)) {
                $result->maxstudents = get_string('na', 'eliswidget_teachingplan');
            }
            $result->enrolled = $result->numenrol.' / '. $result->inprogress;
            if (!empty($result->waiting)) {
                $result->enrolled .= ' ('.$result->waiting.')';
            }
            $result->instructors = get_config('eliswidget_teachingplan', 'instructors') ? static::get_instructors($result->element_id) : '';
            $result->classtime = get_string('date_na', 'eliswidget_teachingplan'); // TBD?
            if (isset($result->starttimehour) && $result->starttimehour >= 0 && $result->starttimehour <= 23) {
                 $result->classtime = sprintf('%02d:%02d', $result->starttimehour,
                         (isset($result->starttimeminute) && $result->starttimeminute >= 0 && $result->starttimeminute <= 59) ? $result->starttimeminute : 0);
            }
            if (isset($result->endtimehour) && $result->endtimehour >= 0 && $result->endtimehour <= 23) {
                 $result->classtime .= get_string('timerange', 'eliswidget_teachingplan');
                 $result->classtime .= sprintf('%02d:%02d', $result->endtimehour,
                         (isset($result->endtimeminute) && $result->endtimeminute >= 0 && $result->endtimeminute <= 59) ? $result->endtimeminute : 0);
            }
            $pageresultsar[$id] = $result;
            if (!empty($pageresultsar[$id]->startdate)) {
                $pageresultsar[$id]->startdate = userdate($pageresultsar[$id]->startdate, $dateformat);
            } else {
                $pageresultsar[$id]->startdate = get_string('date_na', 'eliswidget_teachingplan');
            }
            if (!empty($pageresultsar[$id]->enddate)) {
                $pageresultsar[$id]->enddate = userdate($pageresultsar[$id]->enddate, $dateformat);
            } else {
                $pageresultsar[$id]->enddate = get_string('date_na', 'eliswidget_teachingplan');
            }
            if (!empty($pageresultsar[$id]->moodletime)) {
                $pageresultsar[$id]->moodletime = userdate($pageresultsar[$id]->moodletime, $dateformat);
            } else {
                $pageresultsar[$id]->moodletime = get_string('date_na', 'eliswidget_teachingplan');
            }
        }
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
