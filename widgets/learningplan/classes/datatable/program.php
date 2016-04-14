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

namespace eliswidget_learningplan\datatable;

/**
 * A datatable implementation for lists of programs - top-level.
 */
class program extends \eliswidget_common\datatable\program {
    /** @var int The number of results displayed per page of the table. */
    const RESULTSPERPAGE = 1000;

    /** @var string The widget's franken-style name. */
    protected $pluginname = 'eliswidget_learningplan';

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
            $result->description = get_string('program_description_format', 'eliswidget_learningplan', $result);
            $result->header = get_string('program_header', 'eliswidget_learningplan', $result);
            $result->numcrssets = \programcrsset::count(new \field_filter('prgid', $id), $DB);
            if (!empty($this->progressbarenabled) && $result->reqcredits > 0 && ($pgmstu = new \curriculumstudent(['curriculumid' => $id, 'userid' => $euserid]))) {
                $pgmstu->load();
                $result->pctcomplete = $pgmstu->get_percent_complete();
            } else {
                $result->pctcomplete = -1; // N/A.
            }
            $pageresultsar[$id] = $result;
        }

        // If idnumber/name filters match 'Non[ -]Program' or no filtering then add Non-Program section.
        $nonprogram = false;
        if ((isset($filters['idnumber']) && isset($filters['idnumber'][0]) && stripos('non-program', $filters['idnumber'][0]) !== false) ||
                (isset($filters['name']) && isset($filters['name'][0]) && stripos('non-program', $filters['name'][0]) !== false)) {
            $nonprogram = true;
        }
        if (!$nonprogram && ((!isset($filters['idnumber']) && !isset($filters['name'])) || (isset($filters['idnumber']) && empty($filters['idnumber'][0])) ||
                (isset($filters['name']) && empty($filters['name'][0])))) {
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
                $nonprogramobj->element_name = get_string('nonprogramcourses', 'eliswidget_learningplan');
                $nonprogramobj->header = get_string('nonprogramcourses', 'eliswidget_learningplan');
                $nonprogramobj->numcrssets = 0;
                $nonprogramobj->pctcomplete = -1; // N/A.
                $pageresultsar['none'] = $nonprogramobj;
            }
        }
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
