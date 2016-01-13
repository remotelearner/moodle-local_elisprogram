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
 * A datatable implementation that lists courses a user is enroled in, but which are not part of a program the user
 * is enroled in.
 */
class nonprogramclasses extends moodleclass {
    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_crs';

    /**
     * Converts an array of requested filter data into an SQL WHERE clause.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters = array()) {
        $filters[] = ['sql' => '(stu.id IS NOT NULL OR waitlist.id IS NOT NULL)'];
        $filters[] = ['sql' => 'NOT EXISTS (SELECT \'x\' FROM {'.\curriculumcourse::TABLE.'} pgmcrs
                                              JOIN {'.\curriculumstudent::TABLE.'} pgmstu ON pgmstu.curriculumid = pgmcrs.curriculumid
                                             WHERE pgmstu.userid = '.$this->userid.'
                                                   AND pgmcrs.courseid = element.id)'];
        $filters[] = ['sql' => 'NOT EXISTS (SELECT \'x\' FROM {'.\crssetcourse::TABLE.'} crssetcrs
                                              JOIN {'.\programcrsset::TABLE.'} pgmcrsset ON crssetcrs.crssetid = pgmcrsset.crssetid
                                              JOIN {'.\curriculumstudent::TABLE.'} pgmstu2 ON pgmstu2.curriculumid = pgmcrsset.prgid
                                             WHERE pgmstu2.userid = '.$this->userid.'
                                                   AND crssetcrs.courseid = element.id)'];
        return parent::get_filter_sql($filters);
    }

    /**
     * Get an array of fields to select in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of fields to select.
     */
    protected function get_select_fields(array $filters = array()) {
        $selectfields = parent::get_select_fields($filters);
        $selectfields[] = '1 AS numenrol';
        $selectfields[] = 'stu.completestatusid AS higheststatus';
        return $selectfields;
    }

    /**
     * Get an ORDER BY sql fragment to be used in the get_search_results method.
     *
     * @return string An ORDER BY sql fragment, if desired.
     */
    protected function get_sort_sql() {
        return 'ORDER BY element.idnumber ASC, stu.completestatusid DESC';
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
