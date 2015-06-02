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
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Onwards Remote Learner.net Inc http://www.remote-learner.net
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

/**
 * A filter to change whether available classes, not-available classes ... are displayed.
 */
class deepsight_filter_classstatus extends deepsight_filter_menuofchoices {
    /** @var array $choices array of filter options */
    protected $choices = array(
        'notcompleted' => '',
        'passed' => '',
        'failed' => '',
        'available' => '',
        'notavailable' => ''
    );

    /** @var string $default choice */
    protected $default = '';

    /** @var int $userid */
    protected $userid = 0;

    /** @var int $programid */
    protected $programid = 0;

    /**
     * Constructor.
     *
     * @param moodle_database &$DB       The global moodle_database object.
     * @param string          $name      The name of the filter. Used when receiving data to determine where to send the data.
     * @param string          $label     The label that will be displayed on the filter button.
     * @param array           $fielddata An array of field information used by the filter. Formatted like [field]=>[label].
     *                                   Usually this is what field the filter will use to affect the datatable results, but refer
     *                                   to the individual filter for specifics.
     * @param string          $endpoint  The endpoint to make requests to, when searching for a choice.
     */
    public function __construct(moodle_database &$DB, $name, $label, array $fielddata = array(), $endpoint = null) {
        if (isset($fielddata['userid'])) {
            $this->userid = $fielddata['userid'];
            unset($fielddata['userid']);
        }
        if (isset($fielddata['programid'])) {
            $this->programid = $fielddata['programid'];
            unset($fielddata['programid']);
        }
        parent::__construct($DB, $name, $label, $fielddata, $endpoint);
        $this->postconstruct();
    }

    /**
     * Sets the available choices - not enrolled, enrolled, or all.
     */
    public function postconstruct() {
        $this->choices['available'] = get_string('ds_available', 'local_elisprogram');
        $this->choices['notavailable'] = get_string('ds_notavailable', 'local_elisprogram');
        $this->choices['notcompleted'] = get_string('ds_notcompleted', 'local_elisprogram');
        $this->choices['passed'] = get_string('ds_passed', 'local_elisprogram');
        $this->choices['failed'] = get_string('ds_failed', 'local_elisprogram');
    }

    /**
     * Set the default choice.
     *
     * @param string $default The default choice (corresponds to an index of $this->choices).
     */
    public function set_default($default) {
        $this->default = $default;
    }

    /**
     * Set the user ID we're using to filter.
     *
     * @param int $userid The user ID to set.
     */
    public function set_userid($userid) {
        if (is_int($userid)) {
            $this->userid = $userid;
        }
    }

    /**
     * Get the set user ID.
     *
     * @return int The current user ID.
     */
    public function get_userid() {
        return $this->userid;
    }

    /**
     * Set the Program ID we're using to filter.
     *
     * @param int $prgid The program ID to set.
     */
    public function set_programid($prgid) {
        if (is_int($prgid)) {
            $this->programid = $prgid;
        }
    }

    /**
     * Get the set Program ID.
     *
     * @return int The current Program ID.
     */
    public function get_programid() {
        return $this->programid;
    }

    /**
     * Get SQL to show only users that fit into the currently selected option.
     *
     * Will force an enrolment to be present, force an enrolment to not be preset, or return empty SQL.
     *
     * @param mixed $data The data from the filter send from the javascript.
     * @return array An array consisting of filter sql as index 0, and an array of parameters as index 1
     */
    public function get_filter_sql($data) {
        if (empty($this->userid)) {
            throw new Exception('No userid set for classstatus filter.');
        }
        $data = (!empty($data) && is_array($data)) ? $data : explode(',', $this->default);
        $sql = array();
        $params = array();
        $timenow = time();
        foreach ($data as $option) {
            if ($option == 'notavailable') {
                // Already enrolled/complete, Pre-reqs not met, class full, > endtime, ...
                $sql[] = '(element.enddate > 0 AND element.enddate < '.$timenow.')';
                $sql[] = 'EXISTS (SELECT "x"
                                    FROM {local_elisprogram_cls_enrol} stu0
                                    JOIN {local_elisprogram_cls} cls0 ON stu0.classid = cls0.id
                                   WHERE cls0.courseid = element.courseid
                                         AND stu0.userid = ?)';
                $sql[] = 'EXISTS (SELECT "x"
                                    FROM {local_elisprogram_waitlist} waitlist1
                                    JOIN {local_elisprogram_cls} cls1 ON waitlist1.classid = cls1.id
                                   WHERE cls1.courseid = element.courseid
                                         AND waitlist1.userid = ?)';
                $params = array_merge($params, array($this->userid, $this->userid));
                // $sql[] = '(element.maxstudents > 0 AND element.maxstudents >= totalstudents)';
                if (!empty($this->programid)) {
                    $sql[] = 'EXISTS (SELECT "x"
                                        FROM {local_elisprogram_pgm_crs} prgcrs
                                        JOIN {local_elisprogram_crs_prereq} prereq ON prereq.curriculumcourseid = prgcrs.id
                                       WHERE prgcrs.curriculumid = ?
                                             AND prgcrs.courseid = element.courseid
                                             AND NOT EXISTS (SELECT "x"
                                                               FROM {local_elisprogram_cls_enrol} stu2
                                                               JOIN {local_elisprogram_cls} cls2 ON stu2.classid = cls2.id
                                                              WHERE cls2.courseid = prereq.courseid
                                                                    AND stu2.userid = ?
                                                                    AND stu2.completestatusid = '.STUSTATUS_PASSED.'))';
                    $params = array_merge($params, array($this->programid, $this->userid));
                }
            } else if ($option == 'available') {
                $where = '((element.enddate = 0 OR element.enddate >= '.$timenow.')';
                // $where .= ' AND (element.maxstudents = 0 OR element.maxstudents < totalstudents)';
                $where .= ' AND NOT EXISTS (SELECT "x"
                                              FROM {local_elisprogram_cls_enrol} stu3
                                              JOIN {local_elisprogram_cls} cls3 ON stu3.classid = cls3.id
                                             WHERE cls3.courseid = element.courseid
                                                   AND stu3.userid = ?)';
                $where .= ' AND NOT EXISTS (SELECT "x"
                                              FROM {local_elisprogram_waitlist} waitlist4
                                              JOIN {local_elisprogram_cls} cls4 ON waitlist4.classid = cls4.id
                                             WHERE cls4.courseid = element.courseid
                                                   AND waitlist4.userid = ?)';
                $params = array_merge($params, array($this->userid, $this->userid));
                if (!empty($this->programid)) {
                    $where .= ' AND NOT EXISTS (SELECT "x"
                                                  FROM {local_elisprogram_pgm_crs} prgcrs1
                                                  JOIN {local_elisprogram_crs_prereq} prereq1 ON prereq1.curriculumcourseid = prgcrs1.id
                                                 WHERE prgcrs1.curriculumid = ?
                                                       AND prgcrs1.courseid = element.courseid
                                                       AND NOT EXISTS (SELECT "x"
                                                                         FROM {local_elisprogram_cls_enrol} stu5
                                                                         JOIN {local_elisprogram_cls} cls5 ON stu5.classid = cls5.id
                                                                        WHERE cls5.courseid = prereq1.courseid
                                                                              AND stu5.userid = ?
                                                                              AND stu5.completestatusid = '.STUSTATUS_PASSED.'))';
                    $params = array_merge($params, array($this->programid, $this->userid));
                }
                $where .= ')';
                $sql[] = $where;
            } else if ($option == 'notcompleted') {
                $sql[] = '(enrol.id IS NOT NULL AND enrol.completestatusid = '.STUSTATUS_NOTCOMPLETE.')';
                $sql[] = 'waitlist.id IS NOT NULL';
            } else if ($option == 'passed') {
                $sql[] = '(enrol.id IS NOT NULL AND enrol.completestatusid = '.STUSTATUS_PASSED.')';
            } else if ($option == 'failed') {
                $sql[] = '(enrol.id IS NOT NULL AND enrol.completestatusid = '.STUSTATUS_FAILED.')';
            }
        }
        if (empty($sql)) {
            return array('', array());
        }
        $allsql = '('.implode(' OR ', $sql).')';
        return array($allsql, $params);
    }

    /**
     * Get select fields required.
     *
     * @return array An array consisting of the select fields.
     */
    public function get_select_fields() {
        return array();
    }

    /**
     * Returns options for the javascript object.
     *
     * @return array An array of options.
     */
    public function get_js_opts() {
        $opts = parent::get_js_opts();
        $opts['no_search'] = true;
        return $opts;
    }
}
