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
        if ($this->progressbarenabled) {
            $selectfields[] = 'COUNT(DISTINCT stu.id) AS numenrol';
            $selectfields[] = 'COUNT(DISTINCT stu2.id) AS completedusers';
        }
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
        global $CFG;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/pmclass.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/instructor.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/student.class.php');
        list($sql, $params) = parent::get_join_sql($filters);
        $newsql = [
                'JOIN {'.\pmclass::TABLE.'} cls ON cls.courseid = element.id',
                'JOIN {'.\instructor::TABLE.'} ins ON ins.classid = cls.id
                      AND ins.userid = ?'
        ];
        if ($this->progressbarenabled) {
             $newsql[] = 'LEFT JOIN {'.\student::TABLE.'} stu ON stu.classid = cls.id';
             $newsql[] = 'LEFT JOIN {'.\student::TABLE.'} stu2 ON stu2.classid = cls.id
                                    AND stu2.completestatusid > '.STUSTATUS_NOTCOMPLETE;
        }
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
     * Get a list of Programs/CourseSets associated to the specified Course Description.
     *
     * @param int $courseid The ELIS Course Description id.
     * @return string 'Pretty' list of Programs/CourseSets associated to the specified Course Description.
     */
    public static function get_course_programs($courseid) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/curriculumcourse.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/crssetcourse.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/programcrsset.class.php');
        $ret = '';
        $programids = [];
        $currcourses = \curriculumcourse::find(new \field_filter('courseid', $courseid));
        if ($currcourses && $currcourses->valid()) {
            foreach ($currcourses as $currcourse) {
                $programids[$currcourse->curriculumid] = $currcourse->curriculumid;
                if (!empty($ret)) {
                    $ret .= ', ';
                }
                $prg = new \curriculum($currcourse->curriculumid);
                $ret .= $prg->idnumber;
                if ($currcourse->required) {
                    $ret .= '*';
                }
            }
            unset($currcourses);
        }

        // Now find CourseSets in Programs not already listed ...
        $prgcrssets = [];
        $crssetcourses = \crssetcourse::find(new \field_filter('courseid', $courseid));
        if ($crssetcourses && $crssetcourses->valid()) {
            foreach ($crssetcourses as $crssetcourse) {
                $crssetprgs = \programcrsset::find(new \field_filter('crssetid', $crssetcourse->crssetid));
                if ($crssetprgs && $crssetprgs->valid()) {
                    foreach ($crssetprgs as $crssetprg) {
                        if (!isset($programids[$crssetprg->prgid])) {
                            if (!isset($prgcrssets[$crssetprg->prgid])) {
                                $prgcrssets[$crssetprg->prgid] = [];
                            }
                            $prgcrssets[$crssetprg->prgid][] = $crssetcourse->crssetid;
                        }
                    }
                    unset($crssetprgs);
                }
            }
            unset($crssetcourses);
        }

        foreach ($prgcrssets as $prgid => $crssets) {
            if (!empty($ret)) {
                $ret .= ', ';
            }
            $prg = new \curriculum($prgid);
            $ret .= $prg->idnumber.' ('.get_string('coursesets', 'eliswidget_teachingplan').' ';
            $first = true;
            foreach ($crssets as $crssetid) {
                if (!$first) {
                    $ret .= ', ';
                }
                $first = false;
                $crsset = new \courseset($crssetid);
                $ret .= $crsset->idnumber;
            }
            $ret .= ')';
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
        foreach ($pageresults as $id => $result) {
            $result->wwwroot = $CFG->wwwroot;
            $result->header = get_string('course_header', 'eliswidget_teachingplan', $result);
            unset($result->wwwroot);
            $result->programs = static::get_course_programs($id);
            $result->pctcomplete = -1;
            if ($this->progressbarenabled && !empty($result->numenrol)) {
                $result->pctcomplete = $result->completedusers * 100.0 / (float)$result->numenrol;
            }
            $pageresultsar[$id] = $result;
        }
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
