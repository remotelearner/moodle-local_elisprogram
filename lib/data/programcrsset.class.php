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
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');
require_once(elis::lib('data/data_object.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/courseset.class.php'));

class programcrsset extends elis_data_object {
    /* @const string the DB table */
    const TABLE = 'local_elisprogram_prgcrsset';

    /* @var string the class's verbose name */
    protected $verbose_name = 'programcourseset';

    /* DB fields: */
    protected $_dbfield_id;
    protected $_dbfield_crssetid;
    protected $_dbfield_prgid;
    protected $_dbfield_reqcredits;
    protected $_dbfield_reqcourses;
    protected $_dbfield_andor;
    protected $_dbfield_timemodified;
    protected $_dbfield_timecreated;

    /* @var array the class's associations */
    public static $associations = array(
        'program' => array(
            'class' => 'curriculum',
            'idfield' => 'prgid'
        ),
        'crsset' => array(
            'class' => 'courseset',
            'idfield' => 'crssetid'
        )
    );

    /* @var array validation rules */
    public static $validation_rules = array(
        array('validation_helper', 'is_unique_prgid_crssetid')
    );

    /**
     * get_verbose_name method to return human-readable name of entity
     * @return string the verbose name of entity
     */
    public function get_verbose_name() {
        return $this->verbose_name;
    }

    /**
     * toString method to return entity id
     * @return string the entity id
     */
    public function __toString() {
        return (string)$this->id; // TBD
    }

    /**
     * set_from_data method to initialize data object with specified data
     * @param object|array the data
     */
    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }

    /**
     * is_complete method to determine if a crsset has been completed for the program
     * @param int $userid the user to check courseset completion on
     * @param float $percentcomplete reference param to return percentage of courseset complete
     * @return bool true if complete, false otherwise
     */
    public function is_complete($userid, &$percentcomplete = null) {
        if ($percentcomplete != null) {
            $percentcomplete = 100.0;
        }
        if ($this->reqcredits <= 0.0 || $this->reqcourses <= 0) {
            return true;
        }
        require_once(elispm::lib('data/pmclass.class.php'));
        require_once(elispm::lib('data/student.class.php'));
        $complete = true;
        $curcredits = 0.0;
        $curcourses = 0;
        $courses = $this->crsset->courses;
        if (!$courses || !$courses->valid()) {
            return true;
        }
        $crsarray = array();
        foreach ($courses as $crs) {
            $crsarray[] = $crs->id;
        }
        $crsarray = array_unique($crsarray);
        if (empty($crsarray)) {
            return true;
        }
        list($inorequal, $params) = $this->_db->get_in_or_equal($crsarray);
        $enrolmentsql = 'SELECT cce.id, cce.completestatusid, cce.credits
                           FROM {'.pmclass::TABLE.'} cls
                           JOIN {'.student::TABLE."} cce ON cls.id = cce.classid
                                AND cce.userid = ?
                          WHERE cls.courseid {$inorequal}";
        $params = array_merge(array($userid), $params);
        $enrolments = $this->_db->get_recordset_sql($enrolmentsql, $params);
        foreach ($enrolments as $enrolment) {
            if ($enrolments->completestatusid != STUSTATUS_NOTCOMPLETE) {
                $curcourses++;
                $curcredits += $enrolment->credits;
            }
        }
        $prcntcomplete = 100.0;
        if ($this->reqcredits > 0.0 && $curcredits < $this->reqcredits) {
            $prcntcomplete = $curcredits / $this->reqcredits * 100.0;
            $complete = false;
        }
        if ($this->reqcourses > 0 && ($complete == false || $this->andor)) {
            if ($curcourses < $this->reqcourses) {
                $prcntcrs = $curcourses / $this->reqcourses * 100.0;
                if ($prcntcomplete < 100.0) {
                    if (!$this->andor) {
                        $prcntcomplete = max($prcntcomplete, $prcntcrs);
                    } else {
                        $prcntcomplete = $prcntcomplete / 2.0 + $prcntcrs / 2.0;
                    }
                } else {
                    $prcntcomplete = $prcntcrs;
                }
                $complete = false;
            } else if (!$this->andor) {
                $complete = true;
                $prcntcomplete = 100.0;
            }
        }
        if ($percentcomplete != null) {
            $percentcomplete = $prcntcomplete;
        }
        return $complete;
    }
}