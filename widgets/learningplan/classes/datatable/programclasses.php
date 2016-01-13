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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    eliswidget_learningplan
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_learningplan\datatable;

/**
 * A datatable implementation that lists courses for a program.
 */
class programclasses extends moodleclass {
    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_pgm_crs';

    /** @var int The ID of the program we're getting classes for. */
    protected $programid = null;

    /**
     * Get an array of fields to select in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of fields to select.
     */
    protected function get_select_fields(array $filters = array()) {
        require_once(\elispm::lib('data/user.class.php'));
        $euserid = $this->userid;
        $selectfields = parent::get_select_fields($filters);

        // Select the number of prerequisites not met.
        $selectfields[] = '(SELECT count(prereq.id)
                              FROM {local_elisprogram_crs_prereq} prereq
                             WHERE prereq.curriculumcourseid = element.id
                                   AND NOT EXISTS (SELECT \'x\' FROM {local_elisprogram_cls_enrol} innerstu
                                                     JOIN {local_elisprogram_cls} innercls ON innerstu.classid = innercls.id
                                                    WHERE innercls.courseid = prereq.courseid
                                                          AND innerstu.userid = '.$euserid.'
                                                          AND innerstu.completestatusid = '.STUSTATUS_PASSED.')

                           ) AS numnoncompleteprereq';
        $selectfields[] = 'COUNT(stu.id) AS numenrol';
        $selectfields[] = 'MAX(stu.completestatusid) AS higheststatus';
        $selectfields[] = 'COUNT(waitlist.id) AS numwaitlist';
        return $selectfields;
    }

    /**
     * Set the ID of the program we're getting program classes for.
     *
     * @param int $programid The ID of the program we're getting classes for.
     */
    public function set_programid($programid) {
        $this->programid = $programid;
    }

    /**
     * Converts an array of requested filter data into an SQL WHERE clause.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters = array()) {
        $filters[] = ['sql' => 'element.curriculumid = ?', 'params' => [$this->programid]];
        return parent::get_filter_sql($filters);
    }

    /**
     * Get an ORDER BY sql fragment to be used in the get_search_results method.
     *
     * @return string An ORDER BY sql fragment, if desired.
     */
    protected function get_sort_sql() {
        return 'ORDER BY element.position ASC, crs.idnumber ASC, stu.completestatusid DESC, waitlist.id DESC';
    }

    /**
     * Get a GROUP BY sql fragment to be used in the get_search_results method.
     *
     * @return string A GROUP BY sql fragment, if desired.
     */
    protected function get_groupby_sql() {
        return 'GROUP BY element.id, cls.id';
    }
}
