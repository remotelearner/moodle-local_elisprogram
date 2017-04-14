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
require_once(elis::lib('data/data_object_with_custom_fields.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('lib.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/courseset.class.php'));
require_once(elispm::lib('data/programcrsset.class.php'));
require_once(elispm::lib('data/crssetcourse.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('datedelta.class.php'));

class curriculum extends data_object_with_custom_fields {
    const TABLE = 'local_elisprogram_pgm';

    var $verbose_name = 'program';

    static $associations = array(
        'clustercurriculum' => array(
            'class' => 'clustercurriculum',
            'foreignidfield' => 'curriculumid'
        ),
        'curriculumstudent' => array(
            'class' => 'curriculumstudent',
            'foreignidfield' => 'curriculumid'
        ),
        'curriculumcourse' => array(
            'class' => 'curriculumcourse',
            'foreignidfield' => 'curriculumid'
        ),
        'track' => array(
            'class' => 'track',
            'foreignidfield' => 'curid'
        ),
        'crssets' => array(
            'class' => 'programcrsset',
            'foreignidfield' => 'prgid'
        )
    );

    protected $_dbfield_idnumber;
    protected $_dbfield_name;
    protected $_dbfield_description;
    protected $_dbfield_reqcredits;
    protected $_dbfield_iscustom;
    protected $_dbfield_timecreated;
    protected $_dbfield_timemodified;
    protected $_dbfield_timetocomplete;
    protected $_dbfield_frequency;
    protected $_dbfield_priority;

    static $delete_is_complex = true;

    protected function get_field_context_level() {
        return CONTEXT_ELIS_PROGRAM;
    }

    public function set_from_data($data) {

        $fields = field::get_for_context_level(CONTEXT_ELIS_PROGRAM);
        $fields = $fields ? $fields : array();
        foreach ($fields as $field) {
            $fieldname = "field_{$field->shortname}";
            if (isset($data->$fieldname)) {
                $this->$fieldname = $data->$fieldname;
            }
        }

        $this->_load_data_from_record($data, true);
    }

	function delete() {
        // delete associated data
        require_once elis::lib('data/data_filter.class.php');

        //filter specific for tracks, due to different field name
        $filter = new field_filter('curid', $this->id);
        track::delete_records($filter, $this->_db);

        //filter for all other associations
        $filter = new field_filter('curriculumid', $this->id);
        clustercurriculum::delete_records($filter, $this->_db);
        curriculumcourse::delete_records($filter, $this->_db);
        curriculumstudent::delete_records($filter, $this->_db);

        parent::delete();

        //clean up the curriculum context instance
        $context = \local_elisprogram\context\program::instance($this->id);
        $context->delete();
    }

    function __toString() {
        return $this->name;
    }

    /**
     * Check for a duplicate record when doing an insert.
     *
     * @param boolean $record true if a duplicate is found false otherwise
     * note: output is expected and treated as boolean please ensure return values are boolean
     */
    function duplicate_check($record=null) {
        if(empty($record)) {
            $record = $this;
        }

        /// Check for valid idnumber - it can't already exist in the user table.
        if ($this->_db->record_exists($this->table, array('idnumber'=>$record->idnumber))) {
            return true;
        }

        return false;
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  STATIC FUNCTIONS:                                              //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    /**
     * Check for any curriculum nags that need to be handled.
     *
     */
    public static function check_for_nags() {
        $status = self::check_for_completed_nags();
        $status = self::check_for_recurrence_nags() && $status;
        return $status;
    }

    /**
     * Check for any curriculum completed nags that need to be handled.
     * @return boolean True if successful, otherwise false.
     */
    public static function check_for_completed_nags() {
        global $CFG, $DB;

        $curid = 0;
        $reqcredits = 10000; // Initially so a completion event is not triggered.
        $curreqcourses = [];
        $curcourses = [];
        $curcrssets = [];

        // Completed curricula:
        $select = 'timecompleted = 0 AND completed = ?';
        $rs = $DB->get_recordset_select(curriculumstudent::TABLE, $select, [STUSTATUS_NOTCOMPLETE], 'curriculumid');
        if ($rs && $rs->valid()) {
            $clsenrolafterprgenrol = !empty(elis::$config->local_elisprogram->clsenrolafterprgenrol);
            foreach ($rs as $rec) {
                if ($curid != $rec->curriculumid) {
                    $curid = $rec->curriculumid;
                    // Get all courses in program, including courseset courses.
                    $curcrssql = 'SELECT DISTINCT crs.id
                                    FROM {'.course::TABLE.'} crs
                               LEFT JOIN {'.curriculumcourse::TABLE.'} curcrs ON curcrs.curriculumid = ?
                                         AND curcrs.courseid = crs.id
                               LEFT JOIN {'.programcrsset::TABLE.'} pcs ON pcs.prgid = ?
                               LEFT JOIN {'.crssetcourse::TABLE.'} csc ON pcs.crssetid = csc.crssetid
                                         AND crs.id = csc.courseid
                                   WHERE curcrs.id IS NOT NULL
                                         OR (pcs.id IS NOT NULL AND csc.id IS NOT NULL)';
                    $curcourses = $DB->get_records_sql($curcrssql, [$curid, $curid]);
                    if (!empty($curcourses)) {
                        // Get required courses in program.
                        $curreqcrssql = 'SELECT curcrs.courseid
                                           FROM {'.curriculumcourse::TABLE.'} curcrs
                                          WHERE curcrs.required = 1
                                                AND curcrs.curriculumid = ?';
                        $curreqcourses = $DB->get_records_sql($curreqcrssql, [$curid]);
                        $reqcrsparams = [];
                        if (!empty($curreqcourses)) {
                            list($reqcrsinorequal, $reqcrsparams) = $DB->get_in_or_equal(array_keys($curreqcourses));
                            $reqsql = 'SELECT COUNT(DISTINCT cls.courseid)
                                         FROM {'.student::TABLE.'} stu
                                         JOIN {'.pmclass::TABLE."} cls ON stu.classid = cls.id
                                        WHERE cls.courseid {$reqcrsinorequal}
                                              AND stu.userid = ?
                                              AND stu.completestatusid = ?
                                              AND stu.locked = 1
                                              AND stu.completetime > 0";
                            if ($clsenrolafterprgenrol) {
                                $reqsql .= ' AND stu.enrolmenttime >= ?';
                            }
                        }
                        list($crsinorequal, $crsparams) = $DB->get_in_or_equal(array_keys($curcourses));
                        $stusql = 'SELECT MAX(stu.completetime) AS maxcompletetime, SUM(stu.credits) as totalcredits
                                     FROM {'.student::TABLE.'} stu
                                     JOIN {'.pmclass::TABLE."} cls ON stu.classid = cls.id
                                    WHERE cls.courseid {$crsinorequal}
                                          AND stu.userid = ?
                                          AND stu.completestatusid = ?
                                          AND stu.locked = 1
                                          AND stu.completetime > 0";
                        if ($clsenrolafterprgenrol) {
                            $stusql .= ' AND stu.enrolmenttime >= ?';
                        }

                        // Get all CourseSets in Program.
                        $select = 'prgid = ? AND (reqcredits > 0.0 OR reqcourses > 0)';
                        $curcrssets = $DB->get_records_select(programcrsset::TABLE, $select, [$curid], '', 'id');
                        $cur = new curriculum($curid);
                        $reqcredits = $cur->reqcredits;
                    }
                }
                if (empty($curcourses)) {
                    continue;
                }
                // Check for incomplete Program CourseSets ...
                if (!empty($curcrssets)) {
                    $crssetscomplete = true;
                    foreach ($curcrssets as $curcrsset) {
                        $prgcrsset = new programcrsset($curcrsset->id);
                        $prgcrsset->load();
                        if (!$prgcrsset->is_complete($rec->userid)) {
                            $crssetscomplete = false;
                            break;
                        }
                    }
                    if (!$crssetscomplete) {
                        continue;
                    }
                }

                // Check enrolments in Program Classes for completeness ...
                if (!empty($reqcrsparams)) {
                    $reqparams = [$rec->userid, STUSTATUS_PASSED];
                    if ($clsenrolafterprgenrol) {
                        $reqparams[] = $rec->timecreated;
                    }
                    $reqcount = $DB->get_field_sql($reqsql, array_merge($reqcrsparams, $reqparams));
                    if ($reqcount < count($reqcrsparams)) {
                        // error_log("curriculum::check_for_completed_nags(): reqcount = {$reqcount}; required = ".count($reqcrsparams));
                        continue;
                    }
                }
                $stuparams = [$rec->userid, STUSTATUS_PASSED];
                if ($clsenrolafterprgenrol) {
                    $stuparams[] = $rec->timecreated;
                }
                $studata = $DB->get_record_sql($stusql, array_merge($crsparams, $stuparams));
                if (!empty($studata) && $studata->totalcredits >= $reqcredits) {
                    $curstu = new curriculumstudent($rec->id);
                    $curstu->load();
                    $curstu->complete($studata->maxcompletetime, $studata->totalcredits, true);
                }
            }
            $rs->close();
        }

        $sendtouser       = elis::$config->local_elisprogram->notify_curriculumnotcompleted_user;
        $sendtorole       = elis::$config->local_elisprogram->notify_curriculumnotcompleted_role;
        $sendtosupervisor = elis::$config->local_elisprogram->notify_curriculumnotcompleted_supervisor;

        // If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        // Incomplete curricula:
        $select  = 'SELECT cca.id as id, cca.userid, cca.curriculumid, cca.completed, cca.timecompleted, cca.credits,
                    cca.locked, cca.timecreated, cca.certificatecode, cca.timemodified, cur.id as curid,
                    cur.timetocomplete as timetocomplete ';
        $from    = 'FROM {'.curriculumstudent::TABLE.'} cca ';
        $join    = 'INNER JOIN {'.user::TABLE.'} cu ON cu.id = cca.userid
                    INNER JOIN {'.curriculum::TABLE.'} cur ON cca.curriculumid = cur.id
                    LEFT JOIN {'.notificationlog::TABLE.'} cnl ON cnl.fromuserid = cu.id AND cnl.instance = cca.id AND
                    cnl.event = \'curriculum_notcompleted\' ';
        $where   = 'WHERE (cca.completed = 0) AND (cur.timetocomplete != \'\') AND (cur.timetocomplete NOT LIKE \'0h, 0d, 0w, 0m, 0y%\') AND cnl.id IS NULL ';
        $order   = 'ORDER BY cur.id, cca.id ASC ';
        $sql     = $select.$from.$join.$where.$order;

        $context = false;
        $timenow = time();
        $secondsinaday = 60 * 60 * 24;
        $notifiedincompleteprograms = array();

        $rs = $DB->get_recordset_sql($sql);
        if ($rs && $rs->valid()) {
            foreach ($rs as $rec) {
                // Loop through curriculum assignments checking for nags.
                $deltad = new datedelta($rec->timetocomplete);

                // Need to fit this into the SQL instead.
                $reqcompletetime = $rec->timecreated + $deltad->gettimestamp();

                // If no time to completion set, it has no completion restriction.
                if ($reqcompletetime == 0) {
                    continue;
                }

                $daysfrom = ($reqcompletetime - $timenow) / $secondsinaday;
                if ($daysfrom <= elis::$config->local_elisprogram->notify_curriculumnotcompleted_days) {
                    $notifiedincompleteprograms[$rec->curriculumid][$rec->userid] = true;
                    $eventdata = array(
                        'context' => context_system::instance(),
                        'other' => (array)$rec
                    );
                    $event = \local_elisprogram\event\curriculum_notcompleted::create($eventdata);
                    $event->trigger();
                }
            }
            $rs->close();
        }

        return true;
    }

    /**
     * Check for any curriculum recurrence notifications that need to be sent out.
     */
    public static function check_for_recurrence_nags() {
        global $CFG, $DB;

        $sendtouser       = elis::$config->local_elisprogram->notify_curriculumrecurrence_user;
        $sendtorole       = elis::$config->local_elisprogram->notify_curriculumrecurrence_role;
        $sendtosupervisor = elis::$config->local_elisprogram->notify_curriculumrecurrence_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        $timenow = time();

        // Notification offset from expiry time, in seconds
        $notification_offset = DAYSECS * elis::$config->local_elisprogram->notify_curriculumrecurrence_days;

        $sql = 'SELECT cca.id AS enrolmentid, cc.name AS curriculumname, cc.id as curriculumid,
                       cu.id AS userid, cu.idnumber AS useridnumber, cu.firstname AS firstname, cu.lastname AS lastname,
                       mu.id AS muserid
                  FROM {'.curriculumstudent::TABLE.'} cca
                  JOIN {'.curriculum::TABLE.'} cc ON cca.curriculumid = cc.id
                  JOIN {'.user::TABLE.'} cu ON cu.id = cca.userid
                  JOIN {user} mu ON cu.idnumber = mu.idnumber
             LEFT JOIN {'.notificationlog::TABLE.'} cnl ON cnl.fromuserid = cu.id AND cnl.instance = cca.id AND cnl.event = \'curriculum_recurrence\'
                 WHERE cnl.id IS NULL and cca.timeexpired > 0
                  AND cca.timeexpired < ? + '.$notification_offset.'
               ';

        $params = array($timenow);

        $rs = $DB->get_recordset_sql($sql, $params);
        if ($rs) {
            foreach ($rs as $rec) {
                mtrace("Triggering curriculum_recurrence event.\n");
                $eventdata = array(
                    'context' => context_system::instance(),
                    'other' => (array)$rec
                );
                $event = \local_elisprogram\event\curriculum_recurrence::create($eventdata);
                $event->trigger();
            }
        }

        return true;
    }

    /*
     * ---------------------------------------------------------------------------------------
     * EVENT HANDLER FUNCTIONS:
     *
     * These functions handle specific student events.
     *
     */

    /**
     * Function to handle curriculum recurrence events.
     *
     * @param   user     $user  The PM user the curriculum is recurring for
     *
     * @return  boolean         TRUE is successful, otherwise FALSE
     */

    public static function curriculum_recurrence_handler($user) {
        global $CFG, $DB;
        $user = (object)$user->other;

        require_once elispm::lib('notifications.php');

        /// Does the user receive a notification?
        $sendtouser       = elis::$config->local_elisprogram->notify_curriculumrecurrence_user;
        $sendtorole       = elis::$config->local_elisprogram->notify_curriculumrecurrence_role;
        $sendtosupervisor = elis::$config->local_elisprogram->notify_curriculumrecurrence_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        $context = context_system::instance();

        $message = new notification();

        /// Set up the text of the message
        $text = empty(elis::$config->local_elisprogram->notify_curriculumrecurrence_message) ?
                    get_string('notifycurriculumrecurrencemessagedef', 'local_elisprogram') :
                    elis::$config->local_elisprogram->notify_curriculumrecurrence_message;
        $search = array('%%userenrolname%%', '%%programname%%');
        $student = new user($user->userid);
        if (!$student) {
            return true;
        }
        $student->load();
        $replace = array($student->moodle_fullname(), $user->curriculumname);
        $text = str_replace($search, $replace, $text);

        $eventlog = new stdClass();
        $eventlog->event = 'curriculum_recurrence';
        $eventlog->instance = $user->enrolmentid;
        $eventlog->fromuserid = $student->id;
        if ($sendtouser) {
            $message->send_notification($text, $student, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_curriculumrecurrence capability.
            if ($roleusers = get_users_by_capability($context, 'local/elisprogram:notify_programrecurrence')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = pm_get_users_by_capability('user', $student->id, 'local/elisprogram:notify_programrecurrence')) {
                $users = $users + $supervisors;
            }
        }

        foreach ($users as $u) {
            $message->send_notification($text, $u, null, $eventlog);
        }

        return true;
    }

    public static function get_by_idnumber($idnumber) {
        global $DB;

        $retval = $DB->get_record(curriculum::TABLE, array('idnumber'=>$idnumber));

        if(!empty($retval)) {
            $retval = new curriculum($retval->id);
        } else {
            $retval = null;
        }

        return $retval;
    }

    /**
     * Clone a curriculum.
     * @param array $options options for cloning.  Valid options are:
     * - 'tracks': whether or not to clone tracks (default: false)
     * - 'courses': whether or not to clone courses (default: false)
     * - 'classes': whether or not to clone classes (default: false)
     * - 'moodlecourses': whether or not to clone Moodle courses (if they were
     *   autocreated).  Values can be (default: "copyalways"):
     *   - "copyalways": always copy course
     *   - "copyautocreated": only copy autocreated courses
     *   - "autocreatenew": autocreate new courses from course template
     *   - "link": link to existing course
     * - 'targetcluster': the cluster id or cluster object (if any) to
     *   associate the clones with (default: none)
     * @return array array of array of object IDs created.  Key in outer array
     * is type of object (plural).  Key in inner array is original object ID,
     * value is new stdClass ID.  Outer array also has an entry called 'errors',
     * which is an array of any errors encountered when duplicating the
     * object.
     */
    function duplicate(array $options=array()) {
        require_once elispm::lib('data/track.class.php');

        $objs = array('errors' => array());
        if (isset($options['targetcluster'])) {
            $userset = $options['targetcluster'];
            if (!is_object($userset) || !is_a($userset, 'userset')) {
                $options['targetcluster'] = $userset = new userset($userset);
            }
        }

        // Due to lazy loading, we need to pre-load this object
        $this->load();

        // clone main curriculum object
        $clone = new curriculum($this);
        unset($clone->id);

        $idnumber = $clone->idnumber;
        $name = $clone->name;
        if (isset($userset)) {
            $to_append = ' - '. $userset->name;
            // if cluster specified, append cluster's name to curriculum
            $idnumber = append_once($idnumber, $to_append,
                                    array('maxlength' => 95));
            $name = append_once($name, $to_append, array('maxlength' => 59));
        }

        //get a unique idnumber
        $clone->idnumber = generate_unique_identifier(curriculum::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber));

        if ($clone->idnumber != $idnumber) {
            //get the suffix appended and add it to the name
            $parts = explode('.', $clone->idnumber);
            $suffix = end($parts);
            $clone->name = $name.'.'.$suffix;
        } else {
            $clone->name = $name;
        }

        $clone = new curriculum($clone);
        $clone->save();
        $objs['curricula'] = array($this->id => $clone->id);
        $options['targetcurriculum'] = $clone->id;

        // associate with target cluster (if any)
        if (isset($userset)) {
            clustercurriculum::associate($userset->id, $clone->id);
        }

        $courseids = array(); // save created courses

        if (!empty($options['courses'])) {
            // copy courses
            $currcrs = curriculumcourse_get_list_by_curr($this->id);
            if ($currcrs->valid()) {
                $objs['courses'] = array();
                $objs['classes'] = array();
                foreach ($currcrs as $currcrsdata) {
                    $course = new course($currcrsdata->courseid);
                    $courseids[$currcrsdata->courseid] = true;
                    $rv = $course->duplicate($options);
                    if (isset($rv['errors']) && !empty($rv['errors'])) {
                        $objs['errors'] = array_merge($objs['errors'], $rv['errors']);
                    }
                    if (isset($rv['courses'])) {
                        $objs['courses'] = $objs['courses'] + $rv['courses'];
                    }
                    if (isset($rv['classes'])) {
                        $objs['classes'] = $objs['classes'] + $rv['classes'];
                    }

                    // associate with curriculum
                    if (isset($rv['courses'][$course->id])) {
                        $curcrs = new curriculumcourse($currcrsdata);
                        unset($curcrs->id);
                        $curcrs->courseid = $rv['courses'][$course->id];
                        $curcrs->curriculumid = $clone->id;
                        $curcrs->save();
                    }
                }
            }
            unset($currcrs);
            // Copy courses in coursesets
            foreach ($this->crssets as $prgcrsset) {
                // Create new courseset
                $crssetdata = $prgcrsset->crsset->to_object();
                unset($crssetdata->id);
                $idnumber = $crssetdata->idnumber;
                $name = $crssetdata->name;
                // Get a unique idnumber
                $crssetdata->idnumber = generate_unique_identifier(courseset::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber));
                if ($crssetdata->idnumber != $idnumber) {
                    // Get the suffix appended and add it to the name
                    $parts = explode('.', $crssetdata->idnumber);
                    $suffix = end($parts);
                    $crssetdata->name = $name.'.'.$suffix;
                } else {
                    $crssetdata->name = $name;
                }
                $dupcrsset = new courseset($crssetdata);
                // Create courseset courses and courseset-course associations
                foreach ($prgcrsset->crsset->courses as $crssetcrs) {
                    $course = $crssetcrs->course;
                    if (empty($courseids[$course->id])) {
                        $courseids[$course->id] = true;
                        $rv = $course->duplicate($options);
                        if (isset($rv['errors']) && !empty($rv['errors'])) {
                            $objs['errors'] = array_merge($objs['errors'], $rv['errors']);
                        }
                        if (isset($rv['courses'])) {
                            $objs['courses'] = $objs['courses'] + $rv['courses'];
                        }
                        if (isset($rv['classes'])) {
                            $objs['classes'] = $objs['classes'] + $rv['classes'];
                        }
                    }
                    // Create new crssetcourse association on new courseset & dup'd course ???
                    $crssetcrsdata = $crssetcrs->to_object();
                    unset($crssetcrsdata->id);
                    $crssetcrsdata['crssetid'] = $dupcrsset->id;
                    $crssetcrsdata['courseid'] = $rv['courses'][$course->id];
                    $dupcrssetcrs = new crssetcourse($crssetcrsdata);
                }
                // Create new programcrsset association with cloned program & duplicated courseset ???
                $prgcrssetdata = $prgcrsset->to_object();
                unset($prgcrssetdata->id);
                $prgcrssetdata->prgid = $clone->id;
                $prgcrssetdata->crssetid = $dupcrsset->id;
                $prgcrsset = new programcrsset($prgcrssetdata);
                $prgcrsset->save();
            }
        }

        if (!empty($objs['errors'])) {
            return $objs;
        }

        if (!empty($options['tracks'])) {
            // copy tracks
            $tracks = track_get_listing('name', 'ASC', 0, 0, '', '', $this->id);
            if (isset($objs['courses'])) {
                $options['coursemap'] = $objs['courses'];
            }
            if (!empty($tracks)) {
                $objs['tracks'] = array();
                if (isset($objs['courses'])) {
                    $options['coursemap'] = $objs['courses'];
                }
                if (!isset($objs['classes'])) {
                    $objs['classes'] = array();
                }
                foreach ($tracks as $track) {
                    $track = new track($track);
                    $options['classmap'] = $objs['classes'];
                    $rv = $track->duplicate($options);
                    if (isset($rv['errors']) && !empty($rv['errors'])) {
                        $objs['errors'] = array_merge($objs['errors'], $rv['errors']);
                    }
                    if (isset($rv['tracks'])) {
                        $objs['tracks'] = $objs['tracks'] + $rv['tracks'];
                    }
                    if (isset($rv['classes'])) {
                        $objs['classes'] = $objs['classes'] + $rv['classes'];
                    }
                }
            }
        }
        return $objs;
    }

    static $validation_rules = array(
        'validate_idnumber_not_empty',
        'validate_unique_idnumber'
    );

    function validate_idnumber_not_empty() {
        return validate_not_empty($this, 'idnumber');
    }

    function validate_unique_idnumber() {
        return validate_is_unique($this, array('idnumber'));
    }

    public function save() {
        $isnew = empty($this->id);

        parent::save();

        if (!$isnew) {
            // If this setting is changed, we need to update the existing curriclum expiration values (ELIS-1172)
            if ($rs = $this->_db->get_recordset_select(curriculumstudent::TABLE, "timecompleted = 0 AND curriculumid = {$this->id}", null, 'id, userid')) {
                $timenow = time();

                foreach ($rs as $rec) {
                    $update = new stdClass;
                    $update->id           = $rec->id;
                    $update->timeexpired  = calculate_curriculum_expiry(NULL, $this->id, $rec->userid);
                    $update->timemodified = $timenow;

                    $this->_db->update_record(curriculumstudent::TABLE, $update);
                 }

                $rs->close();
            }
        }

        field_data::set_for_context_from_datarecord(CONTEXT_ELIS_PROGRAM, $this);
    }

    function get_verbose_name() {
        return $this->verbose_name;
    }

    /**
     * Method crssets_with_course to return coursesets associated to program containing specified course
     * @param int $courseid the course id to check for
     * @return array associated course sets containing specified course
     */
    public function crssets_with_course($courseid) {
        $crssets = array();
        $select = 'crssetid = ? AND courseid = ?';
        foreach ($this->crssets as $crsset) {
            $params = array($crsset->crssetid, $courseid);
            if ($DB->record_exists_select(crssetcourse::TABLE, $select, $params)) {
                $crssets[] = $crsset;
            }
        }
        return $crssets;
    }
}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)

/**
 * Gets a curriculum listing with specific sort and other filters.
 *
 * @param   string        $sort        Field to sort on.
 * @param   string        $dir         Direction of sort.
 * @param   int           $startrec    Record number to start at.
 * @param   int           $perpage     Number of records per page.
 * @param   string        $namesearch  Search string for curriculum name.
 * @param   string        $alpha       Start initial of curriculum name filter.
 * @param   array         $contexts    Contexts to search (in the form return by
 * @param   int           $userid      The id of the user we are assigning to curricula
 * @uses    $CFG
 * @uses    $DB
 * @uses    $USER
 * @return  object array               Returned records.
 */
function curriculum_get_listing($sort = 'name', $dir = 'ASC', $startrec = 0,
                                $perpage = 0, $namesearch = '', $alpha = '',
                                $contexts = null, $userid = 0) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot .'/local/elisprogram/lib/data/curriculum.class.php');
    require_once($CFG->dirroot .'/local/elisprogram/lib/data/curriculumcourse.class.php');
    require_once($CFG->dirroot .'/local/elisprogram/lib/data/clustercurriculum.class.php');
    require_once($CFG->dirroot.'/local/elisprogram/lib/data/programcrsset.class.php');

    $select = 'SELECT cur.*, (SELECT COUNT(*) FROM {'. curriculumcourse::TABLE .'}
               WHERE curriculumid = cur.id ) as courses ';
    $select .= ', (SELECT COUNT(*) FROM {'.programcrsset::TABLE.'} WHERE prgid = cur.id) as crssets ';
    $tables = 'FROM {'. curriculum::TABLE .'} cur ';
    $join   = ' ';
    $on     = ' ';

    $where = array("cur.iscustom = '0'");
    $params = array();

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $name_like  = $DB->sql_like('name', '?', FALSE);
        $where[]    = "($name_like)";
        $params[]   = "%$namesearch%";
    }

    if ($alpha) {
        $name_like = $DB->sql_like('name', '?', FALSE);
        $where[]   = "($name_like)";
        $params[]  = "$alpha%";
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'curriculum');
        $filter_sql = $filter_object->get_sql(false, 'cur');
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($userid)) {
        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'local/elisprogram:program_enrol_userset_user', $USER->id);

        $clusters = cluster_get_user_clusters($userid);
        $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

        $curriculum_context = pm_context_set::for_user_with_capability('curriculum', 'local/elisprogram:program_enrol', $USER->id);
        $filter_object = $curriculum_context->get_filter('id', 'curriculum');
        $filter_sql = $filter_object->get_sql(false, 'cur');
        if (isset($filter_sql['where'])) {
            $curriculum_filter = $filter_sql['where'];
            $curriculum_params = $filter_sql['where_parameters'];
        }

        if (empty($allowed_clusters)) {
            if (!empty($curriculum_filter)) {
                $where[] = $curriculum_filter;
                if (!empty($curriculum_params)) {
                    $params = array_merge($params, $curriculum_params);
                }
            }
        } else {
            $allowed_clusters_list = implode(',', $allowed_clusters);

            //this allows both the indirect capability and the direct curriculum filter to work
            $where[] = '(
                          cur.id IN (
                            SELECT clstcur.curriculumid
                            FROM {'. clustercurriculum::TABLE .'} clstcur
                            WHERE clstcur.clusterid IN ('. $allowed_clusters_list
                          .')
                        )';
            if (!empty($curriculum_filter)) {
                $cluster_where .= "OR
                          {$curriculum_filter}
                        )";
                if (!empty($curriculum_params)) {
                    $params = array_merge($params, $curriculum_params);
                }
            }
            $where[] = $cluster_where;
        }

    }

    if (!empty($where)) {
        $where = 'WHERE '. implode(' AND ', $where).' ';
    } else {
        $where = '';
    }

    if ($sort) {
        $sort = 'ORDER BY '. $sort .' '. $dir .' ';
    }

    $sql = $select.$tables.$join.$on.$where.$sort;
    return $DB->get_records_sql($sql, $params, $startrec, $perpage);
}

/**
 * Gets a curriculum listing with specific sort and other filters as a recordset.
 *
 * @param   string        $sort        Field to sort on.
 * @param   string        $dir         Direction of sort.
 * @param   int           $startrec    Record number to start at.
 * @param   int           $perpage     Number of records per page.
 * @param   string        $namesearch  Search string for curriculum name.
 * @param   string        $alpha       Start initial of curriculum name filter.
 * @param   array         $contexts    Contexts to search (in the form return by
 * @param   int           $userid      The id of the user we are assigning to curricula
 * @uses    $CFG
 * @uses    $DB
 * @uses    $USER
 * @return  recordset     Returned recordset.
 */
function curriculum_get_listing_recordset($sort = 'name', $dir = 'ASC',
                                          $startrec = 0, $perpage = 0,
                                          $namesearch = '', $alpha = '',
                                          $contexts = null, $userid = 0) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot .'/local/elisprogram/lib/data/curriculum.class.php');
    require_once($CFG->dirroot .'/local/elisprogram/lib/data/curriculumcourse.class.php');
    require_once($CFG->dirroot .'/local/elisprogram/lib/data/clustercurriculum.class.php');
    require_once($CFG->dirroot.'/local/elisprogram/lib/data/programcrsset.class.php');

    $select = 'SELECT cur.*, (SELECT COUNT(*) FROM {'. curriculumcourse::TABLE .
              '} WHERE curriculumid = cur.id ) as courses ';
    $select .= ', (SELECT COUNT(*) FROM {'.programcrsset::TABLE.'} WHERE prgid = cur.id) as crssets ';
    $tables = 'FROM {'. curriculum::TABLE .'} cur ';
    $join   = '';
    $on     = '';

    $params = array();
    $where = array("cur.iscustom = '0'");
    if (!empty($namesearch)) {
        $where[] = '('. $DB->sql_like('name', ':like_param', false) . ')';
        $namesearch = trim($namesearch);
        $params['like_param'] = "%{$namesearch}%";
    }

    if ($alpha) {
        $where[] = '('. $DB->sql_like('name', ':starts_with', false) .')';
        $params['starts_with'] = "{$alpha}%";
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'curriculum');
        $filter_sql = $filter_object->get_sql(false, 'cur');
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($userid)) {
        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'local/elisprogram:program_enrol_userset_user', $USER->id);

        $clusters = cluster_get_user_clusters($userid);
        $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

        $curriculum_context = pm_context_set::for_user_with_capability('curriculum', 'local/elisprogram:program_enrol', $USER->id);
        $filter_object = $curriculum_context->get_filter('id', 'curriculum');
        $filter_sql = $filter_object->get_sql(false, 'cur');
        if (isset($filter_sql['where'])) {
            $curriculum_filter = $filter_sql['where'];
            $curriculum_params = $filter_sql['where_parameters'];
        }

        if (empty($allowed_clusters)) {
            if (!empty($curriculum_filter)) {
                $where[] = $curriculum_filter;
                if (!empty($curriculum_params)) {
                    $params = array_merge($params, $curriculum_params);
                }
            }
        } else {
            $allowed_clusters_list = implode(',', $allowed_clusters);

            //this allows both the indirect capability and the direct curriculum filter to work
            $cluster_where = '(
                          cur.id IN (
                            SELECT clstcur.curriculumid
                            FROM {'. clustercurriculum::TABLE .'} clstcur
                            WHERE clstcur.clusterid IN ('. $allowed_clusters_list
                          .')
                        )';
            if (!empty($curriculum_filter)) {
                $cluster_where .= "OR
                          {$curriculum_filter}
                        )";
                if (!empty($curriculum_params)) {
                    $params = array_merge($params, $curriculum_params);
                }
            }
            $where[] = $cluster_where;
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '. implode(' AND ', $where) .' ';
    } else {
        $where = '';
    }

    if ($sort) {
        $sort = 'ORDER BY '. $sort .' '. $dir .' ';
    }

    $sql = $select.$tables.$join.$on.$where.$sort;
    return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
}

/**
 * Gets a program listing suitable for a select box.
 *
 * @return array Returned records.
 */
function program_get_menu() {
    global $DB;
    return $DB->get_records_menu(curriculum::TABLE, NULL, 'name', 'id,name');
}

function curriculum_count_records($namesearch = '', $alpha = '', $contexts = null) {
    global $DB;

    $where = array("iscustom = '0'");
    $params = array();

    if (!empty($namesearch)) {
        $name_like = $DB->sql_like('name', '?', FALSE);

        $where[] = "($name_like)";
        $params[] = "%$namesearch%";
    }

    if ($alpha) {
        $name_like = $DB->sql_like('name', '?', FALSE);
        $where[] = "($name_like)";
        $params[] = "$alpha%";
    }

    if ($contexts != null) {
        $filter_object = $contexts->get_filter('id', 'curriculum');
        $filter_sql = $filter_object->get_sql();
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    $where = implode(' AND ',$where).' ';

    return $DB->count_records_select(curriculum::TABLE, $where, $params);
}

/**
 * Handler that gets called when the curriculum expiration setting
 * is enabled or disabled
 *
 * @param string $name Shortname of the changed setting
 */
function curriculum_expiration_enabled_updatedcallback($name) {
    global $DB, $SESSION;

    //signal that events resulting from updating settings related to curriculum
    //expiry have been handled for the lifetime of the current script
    $SESSION->curriculum_expiration_toggled = true;

    $enabled = get_config('local_elisprogram', 'enable_curriculum_expiration');

    if ($enabled) {
        curriculumstudent::update_expiration_times();
    }
}

/**
 * Handler that gets called when the curriculum expiration time
 * setting is changed
 *
 * @param string $name Shortname of the changed setting
 */
function curriculum_expiration_start_updatedcallback($name) {
    global $DB, $SESSION;

    if (!empty($SESSION->curriculum_expiration_toggled)) {
        //updating curriculum assignment times has already been handled for the
        //lifetime of the current script (prevent updating records twice)
        return;
    }

    $enabled = get_config('local_elisprogram', 'enable_curriculum_expiration');

    if ($enabled) {
        curriculumstudent::update_expiration_times();
    }
}
