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
 * @copyright  (C) 2015 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_learningplan\datatable;

/**
 * A datatable implementation for lists of classes.
 */
class moodleclass extends \eliswidget_enrolment\datatable\base {
    /** @var int The ID of the user we're getting classes for. */
    protected $userid = null;

    /** @var int The number of results displayed per page of the table. */
    const RESULTSPERPAGE = 1000;

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
        $euserid = $this->userid ? $this->userid : \user::get_current_userid();
        $langcoursestatus = get_string('course_status', 'local_elisprogram');
        $coursestatusfilter = new \deepsight_filter_coursestatus($this->DB, 'coursestatus', $langcoursestatus, [
            'stu.userid' => $euserid,
            'tablealias' => 'stu'], $CFG->wwwroot.'/local/elisprogram/widgets/learningplan/ajax.php');
        $coursestatusfilter->set_default('');
        $coursestatusfilter->set_userid($euserid);
        return [$coursestatusfilter];
    }

    /**
     * Get an array of fields to select in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of fields to select.
     */
    protected function get_select_fields(array $filters = array()) {
        $selectfields = parent::get_select_fields($filters);
        if ($this->maintable == \course::TABLE) {
            $selectfields[] = 'element.idnumber AS idnumber';
            $selectfields[] = 'element.name AS name';
            $selectfields[] = 'element.code AS code';
        } else {
            $selectfields[] = 'crs.idnumber AS idnumber';
            $selectfields[] = 'crs.name AS name';
            $selectfields[] = 'crs.code AS code';
        }
        $selectfields[] = 'mdlcrs.id AS moodlecourseid';
        $selectfields[] = 'mdlcrs.fullname AS coursefullname';
        $selectfields[] = 'mdlcrs.shortname AS courseshortname';
        $selectfields[] = 'mdlcrs.idnumber AS courseidnumber';
        $selectfields[] = 'stu.id AS enrol_id';
        $selectfields[] = 'stu.grade AS grade';
        $selectfields[] = 'stu.completestatusid AS completestatusid';
        $selectfields[] = 'stu.completetime AS completetime';
        $selectfields[] = 'waitlist.id AS waitlist_id';
        return $selectfields;
    }

    /**
     * Set the ID of the user we're getting Moodle classes for.
     *
     * @param int $userid The ID of the user we're getting Moodle classes for.
     */
    public function set_userid($userid) {
        $this->userid = $userid;
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
        // error_log("classes/datatable/moodleclass.php::get_join_sql(): this->maintable = {$this->maintable}");
        if ($this->maintable == \course::TABLE) {
            $newsql = ['JOIN {'.\pmclass::TABLE.'} cls ON cls.courseid = element.id'];
        } else { // Main table is local_elisprogram_pgm_crs.
            $newsql = [
                    'JOIN {'.\course::TABLE.'} crs ON crs.id = element.courseid',
                    'JOIN {'.\pmclass::TABLE.'} cls ON cls.courseid = crs.id'
            ];
        }
        $newsql = array_merge($newsql, [
                'LEFT JOIN {'.\classmoodlecourse::TABLE.'} clsmdl ON clsmdl.classid = cls.id',
                'LEFT JOIN {course} mdlcrs ON mdlcrs.id = clsmdl.moodlecourseid',
                'LEFT JOIN {'.\student::TABLE.'} stu ON stu.classid = cls.id
                           AND stu.userid = ?',
                'LEFT JOIN {'.\waitlist::TABLE.'} waitlist ON waitlist.classid = cls.id
                           AND waitlist.userid = ?'
        ]);
        $newparams = [$this->userid, $this->userid];
        return [array_merge($sql, $newsql), array_merge($params, $newparams)];
    }

    /**
     * Converts an array of requested filter data into an SQL WHERE clause.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters = array()) {
        $filters[] = ['sql' => '(stu.id IS NOT NULL OR waitlist.id IS NOT NULL)'];
        return parent::get_filter_sql($filters);
    }

    /**
     * Get an ORDER BY sql fragment to be used in the get_search_results method.
     *
     * @return string An ORDER BY sql fragment, if desired.
     */
    protected function get_sort_sql() {
        return 'ORDER BY element.id ASC, stu.completestatusid DESC';
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
        $dateformat = get_string('date_format', 'eliswidget_learningplan');
        foreach ($pageresults as $id => $result) {
            if (!empty($result->moodlecourseid)) {
                $result->header = get_string('moodlecourse_header', 'eliswidget_learningplan', $result);
                // Change Moodle course header to link.
                $mdlcrslink = new \moodle_url('/course/view.php', ['id' => $result->moodlecourseid]);
                $result->header = \html_writer::link($mdlcrslink, $result->header);
            } else {
                $result->header = get_string('eliscourse_header', 'eliswidget_learningplan', $result);
            }
            $pageresultsar[$id] = $result;
            if (isset($pageresultsar[$id]->completetime) && !empty($pageresultsar[$id]->completetime)) {
                $pageresultsar[$id]->completetime = userdate($pageresultsar[$id]->completetime, $dateformat);
            } else {
                $pageresultsar[$id]->completetime = get_string('date_na', 'eliswidget_learningplan');
            }
        }
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
