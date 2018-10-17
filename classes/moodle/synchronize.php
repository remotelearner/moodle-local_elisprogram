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
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

namespace local_elisprogram\moodle;

require_once(__DIR__.'/../../lib/data/course.class.php');
require_once(__DIR__.'/../../lib/data/user.class.php');
require_once(__DIR__.'/../../lib/data/usermoodle.class.php');
require_once(__DIR__.'/../../lib/data/classmoodlecourse.class.php');
require_once(__DIR__.'/../../lib/data/pmclass.class.php');
require_once(__DIR__.'/../../lib/data/student.class.php');
require_once(__DIR__.'/../../lib/data/waitlist.class.php');
require_once(__DIR__.'/../../../../lib/grade/grade_category.php');
require_once(__DIR__.'/../../../../lib/grade/grade_item.php');
require_once(__DIR__.'/../../../../lib/grade/grade_grade.php');

define('MIN_AUTOCHUNK_SECS', 45);
define('MIN_USER_CHUNK', 100);
define('MAX_USER_CHUNK', 2500);
define('DEFAULT_USER_CHUNK', 250);
define('DEFAULT_SYNC_SECS', 200);

/**
 * Wrapper class for grade_grades to overload get_dategraded.
 */
class grade_grade extends \grade_grade {

    const ENABLE_USER_GRADED_EVENT = 0; // Too slow and not that crucial (?).

    private static $elisoptions = [];

    /**
     * Get & save global ELIS options.
     * @param int $syncstarttime The sync start time.
     */
    public static function init_elis_options($syncstarttime) {
        static::$elisoptions['syncstarttime'] = $syncstarttime;
        $gradesyncdateorder = get_config('local_elisprogram', 'gradesyncdateorder');
        if (!empty($gradesyncdateorder)) {
            static::$elisoptions['gradesyncdateorder'] = explode(',', $gradesyncdateorder);
        }
        $gradesyncdebug = get_config('local_elisprogram', 'gradesyncdebug');
        if (!empty($gradesyncdebug) && debugging('', DEBUG_DEVELOPER)) {
            static::$elisoptions['gradesyncdebug'] = 1;
        }
    }

    /**
     * Grade debug output.
     * @param string $msg The message to output.
     */
    public static function gradesync_debug_out($msg) {
        if (!empty(static::$elisoptions['gradesyncdebug'])) {
            error_log("local_elisprogram::GradeSyncDebug: {$msg}");
        }
    }

    /**
     * Returns timestamp when last graded, null if no grade present
     *
     * @return int
     */
    public function get_dategraded() {
        // TODO: HACK - create new fields (MDL-31379).
        global $DB;
        $gradetime = parent::get_dategraded();
        if (is_null($this->finalgrade) and is_null($this->feedback)) {
            return $gradetime;
        } else if ($this->overridden) {
            static::gradesync_debug_out("Grade overridden");
            return $gradetime;
        } else {
            // Check for other configured gradesyncdateorder options.
            if (!empty(static::$elisoptions['gradesyncdateorder']) && is_array(static::$elisoptions['gradesyncdateorder']) &&
                    $gradetime >= static::$elisoptions['syncstarttime']) {
                foreach (static::$elisoptions['gradesyncdateorder'] as $dateoption) {
                    switch ($dateoption) {
                        case 'event':
                            $gradeitem = $DB->get_record('grade_items', ['id' => $this->itemid]);
                            // Check for grade event in log.
                            if ($gradeitem->itemtype == 'course') {
                                if (($logsstime = $DB->get_field('logstore_standard_log', 'MIN(timecreated)', [
                                    'eventname' => '\\core\\event\\course_completed', 'relateduserid' => $this->userid,
                                    'courseid' => $gradeitem->courseid])) && $logsstime < $gradetime) {
                                    $gradetime = $logsstime;
                                    static::gradesync_debug_out('Got time from old log course_completed event: '.userdate($gradetime));
                                    break 2;
                                }
                            } else if (self::ENABLE_USER_GRADED_EVENT) {
                                $other = ['itemid' => $this->itemid, 'overridden' => false, 'finalgrade' => $this->finalgrade];
                                if (($logsstime = $DB->get_field_select('logstore_standard_log', 'MIN(timecreated)',
                                        'eventname = :eventname AND objectid = :objectid AND '.
                                        $DB->sql_compare_text('other', 100).' = '.$DB->sql_compare_text(':other', 100), [
                                        'eventname' => '\\core\\event\\user_graded', 'objectid' => $this->id, 'other' => @serialize($other)])) &&
                                        $logsstime < $gradetime) {
                                    $gradetime = $logsstime;
                                    static::gradesync_debug_out('Got time from old log user_graded event: '.userdate($gradetime));
                                    break 2;
                                }
                            }
                            break;
                        case 'history':
                            // Check for oldest grade_grades_history record with same finalgrade.
                            if (($gghtime = $DB->get_field('grade_grades_history', 'MIN(timemodified)', ['userid' => $this->userid,
                                'itemid' => $this->itemid, 'finalgrade' => $this->finalgrade])) && $gghtime < $gradetime) {
                                $gradetime = $gghtime;
                                static::gradesync_debug_out('Got time from old grade history: '.userdate($gradetime));
                                break 2;
                            }
                            break;
                        case 'creation':
                            // Since timemodified was set after sync start just use grade_grades timecreated???
                            if ($this->timecreated < $gradetime) {
                                $gradetime = $this->timecreated;
                                static::gradesync_debug_out('Got time from timecreated: '.userdate($gradetime));
                                break 2;
                            }
                            break;
                        default:
                            // Invalid option.
                            static::gradesync_debug_out("Invalid gradesyncdateorder option: {$dateoption}");
                            break;
                    }
                }
            }
        }
        return $gradetime;
    }
}

/**
 * Handles Moodle - ELIS synchronization.
 */
class synchronize {

    /** @var object Holds the next record to process when processing completion elements in get_elis_coursecompletion_grades */
    protected $completionelementlastrec = null;

    /** @var moodle_recordset Holds the completion element recordset for get_elis_coursecompletion_grades */
    protected $completionelementrecset = null;

    /**
     * Get syncable users.
     *
     * @param array $muserids (Optional) An array of moodle userids to limit syncing to.
     * @param array $mcourseids (Optional) An array of moodle courseids to limit syncing to.
     * @return moodle_recordset|array An iterable of syncable user information.
     */
    public function get_syncable_users($muserids = null, $mcourseids = null) {
        global $DB, $CFG;

        // If we are filtering for a specific user, add the necessary SQL fragment.
        $gbr = explode(',', $CFG->gradebookroles);
        list($gbrsql1, $gbrparams1) = $DB->get_in_or_equal($gbr, SQL_PARAMS_NAMED, 'gbr1param');
        $gbrparams2 = array();
        if (is_int($muserids) || (is_scalar($muserids) && (string)(int)$muserids === (string)$muserids)) {
            $muserids = [$muserids];
        }
        if ($muserids === null || !is_array($muserids)) {
            list($gbrsql2, $gbrparams2) = $DB->get_in_or_equal($gbr, SQL_PARAMS_NAMED, 'gbr2param');
            // Get all users (or specified user) that are enroled in any Moodle course that is linked to an ELIS class.
            // The first query locates enrolments by role assignments to a course.
            // The second query locates enrolments by role assignments to a course category.
            $sql = "SELECT u.id AS muid,
                           cu.id AS cmid,
                           crs.id AS moodlecourseid,
                           cls.id AS pmclassid,
                           cls.courseid AS pmcourseid,
                           stu.*
                      FROM {user} u
                      JOIN {role_assignments} ra ON u.id = ra.userid
                      JOIN {context} ctx ON ctx.id = ra.contextid
                           AND ctx.contextlevel = ".CONTEXT_COURSE."
                      JOIN {".\usermoodle::TABLE."} umdl ON umdl.muserid = u.id
                      JOIN {".\user::TABLE."} cu ON cu.id = umdl.cuserid
                      JOIN {course} crs ON crs.id = ctx.instanceid
                      JOIN {".\classmoodlecourse::TABLE."} cmc ON cmc.moodlecourseid = crs.id
                      JOIN {".\pmclass::TABLE."} cls ON cls.id = cmc.classid
                 LEFT JOIN {".\student::TABLE."} stu ON stu.userid = cu.id AND stu.classid = cls.id
                     WHERE ra.roleid $gbrsql1
                           AND u.deleted = 0
                  GROUP BY muid, pmclassid
                    UNION
                    SELECT u.id AS muid,
                           cu.id AS cmid,
                           crs.id AS moodlecourseid,
                           cls.id AS pmclassid,
                           cls.courseid AS pmcourseid,
                           stu.*
                      FROM {user} u
                      JOIN {role_assignments} ra ON u.id = ra.userid
                      JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = ".CONTEXT_COURSECAT."
                      JOIN {context} ctx2 ON (ctx2.path LIKE concat('%/',ctx.id,'/%') OR ctx2.path LIKE concat('%/',ctx.id))
                           AND ctx2.contextlevel = ".CONTEXT_COURSE."
                      JOIN {".\usermoodle::TABLE."} umdl ON umdl.muserid = u.id
                      JOIN {".\user::TABLE."} cu ON cu.id = umdl.cuserid
                      JOIN {course} crs ON crs.id = ctx2.instanceid
                      JOIN {".\classmoodlecourse::TABLE."} cmc ON cmc.moodlecourseid = crs.id
                      JOIN {".\pmclass::TABLE."} cls ON cls.id = cmc.classid
                 LEFT JOIN {".\student::TABLE."} stu ON stu.userid = cu.id AND stu.classid = cls.id
                     WHERE ra.roleid $gbrsql2
                           AND u.deleted = 0
                  GROUP BY muid, pmclassid";
            $params = array_merge($gbrparams1, $gbrparams2);
        } else {
            if (empty($muserids)) {
                return [];
            }
            list($museridsql, $museridparams) = $DB->get_in_or_equal($muserids, SQL_PARAMS_NAMED);
            if (false && !empty($mcourseids) && count($mcourseids) < 500) { // Not implemented.
                list($mcourseidsql, $mcourseidparams) = $DB->get_in_or_equal($mcourseids, SQL_PARAMS_NAMED);
            } else {
                $mcourseidsql = '';
                $mcourseidparams = [];
            }
            // Retrieve a single user.
            $sql = "SELECT umdl.muserid AS muid,
                           umdl.cuserid AS cmid,
                           cmc.moodlecourseid AS moodlecourseid,
                           cls.id AS pmclassid,
                           cls.courseid AS pmcourseid,
                           stu.*
                      FROM {".\classmoodlecourse::TABLE."} cmc
                      JOIN {".\usermoodle::TABLE."} umdl ON umdl.muserid {$museridsql}
                      JOIN {".\pmclass::TABLE."} cls ON cls.id = cmc.classid
                 LEFT JOIN {".\student::TABLE."} stu ON stu.userid = umdl.cuserid AND stu.classid = cls.id
                     WHERE cmc.moodlecourseid IN
                           (SELECT crs.id
                              FROM {role_assignments} ra
                              JOIN {context} ctx ON ctx.id = ra.contextid
                                   AND (ctx.contextlevel = ".CONTEXT_COURSECAT." OR ctx.contextlevel = ".CONTEXT_COURSE.")
                              JOIN {context} ctx2 ON ctx2.contextlevel = ".CONTEXT_COURSE."
                                   AND (ctx2.path LIKE concat('%/',ctx.id,'/%') OR ctx2.path LIKE concat('%/',ctx.id))
                              JOIN {course} crs ON (crs.id = ctx2.instanceid
                                   OR (ctx.contextlevel = ".CONTEXT_COURSE." AND crs.id = ctx.instanceid))
                             WHERE ra.userid = umdl.muserid
                                   AND ra.roleid {$gbrsql1})
                  GROUP BY muid, pmclassid";
            $params = array_merge($museridparams, $gbrparams1);
        }
        $users = $DB->get_recordset_sql($sql, $params);
        if (!empty($users) && $users->valid() === true) {
            return $users;
        } else {
            return array();
        }
    }

    /**
     * Get completion element grades for a specific user.
     *
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     * !!!!! THIS FUNCTION MUST BE CALLED WITH ASCENDING $muserid AND $pmclassid PARAMETERS. !!!!!
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     *
     * This is a bit fragile but it a neat way to reduce memory usage. On the first run, a recordset will be created containing
     * completion element grades for all users (if $userpool is 0). When we call it again, it will loop through the recordset and
     * only assemble grades for the requested user. Using the recordset this way allows us minimal memory usage and database calls,
     * and is pretty neat!
     *
     * @param int $userpool Either 0 to fetch grades for all users, or a moodle userid to fetch for one user.
     * @param int $muserid The user we want to get grades for right now.
     * @param int $pmclassid The id of the ELIS class we want grades for.
     * @return array Array of student_grade information, indexed by coursecompletion id.
     */
    public function get_elis_coursecompletion_grades($userpool, $muserid, $pmclassid) {
        global $DB;

        // error_log("synchronize.php::get_elis_coursecompletion_grades({$userpool}, {$muserid}, {$pmclassid})");
        if ($this->completionelementrecset === null) {
            $userfilter = '';
            $params = array();

            if (!empty($userpool)) {
                $userfilter = 'WHERE mu.id = :userid ';
                $params['userid'] = $userpool;
            }

            $sql = "SELECT grades.*, mu.id AS muid, grades.classid AS pmclassid
                      FROM {".\student_grade::TABLE."} grades
                      JOIN {".\user::TABLE."} cu ON grades.userid = cu.id
                      JOIN {".\usermoodle::TABLE."} umdl ON umdl.cuserid = cu.id
                      JOIN {user} mu ON mu.id = umdl.muserid
                           {$userfilter}
                  ORDER BY muid ASC, pmclassid ASC";
            $this->completionelementrecset = $DB->get_recordset_sql($sql, $params);
        }

        // Get student's completion elements.
        $cmgrades = array();

        // NOTE: we use a do-while loop, since $last_rec might be set from the last run, so we need to check it before we load
        // from the database.

        // Need to track whether we're on the first record because of how recordsets work.
        $first = true;
        do {
            if (isset($this->completionelementlastrec->muid)) {

                $muiddiff = ($this->completionelementlastrec->muid > $muserid) ? true : false;
                $pmclassiddiff = ($this->completionelementlastrec->muid == $muserid && $this->completionelementlastrec->pmclassid > $pmclassid)
                        ? true : false;
                if ($muiddiff === true || $pmclassiddiff === true) {
                    // We've reached the end of this student's grades in this class.
                    // Property $this->completionelementlastrec will save this record for the next run).
                    break;
                }

                if ($this->completionelementlastrec->muid == $muserid && $this->completionelementlastrec->pmclassid = $pmclassid) {
                    $cmgrades[$this->completionelementlastrec->completionid] = $this->completionelementlastrec;
                }
            }

            if (!$first) {
                // Not using a cached record, so advance the recordset.
                $this->completionelementrecset->next();
            }

            // Obtain the next record.
            $this->completionelementlastrec = $this->completionelementrecset->current();
            // Signal that we are now within the current recordset.
            $first = false;
        } while ($this->completionelementrecset->valid());

        return $cmgrades;
    }

    /**
     * Returns the grade items associated with the courses.
     *
     * @param array $courseids Array of course ids.
     * @return array Array of grade_items for each course level grade item.
     */
    public static function fetch_course_items(array $courseids) {
        global $DB;

        $courseitems = array();

        if (!empty($courseids)) {
            list($courseidssql, $courseidsparams) = $DB->get_in_or_equal($courseids);
            $select = 'courseid '.$courseidssql.' AND itemtype = ?';
            $params = array_merge($courseidsparams, array('course'));
            $rs = $DB->get_recordset_select('grade_items', $select, $params);

            $courseitems = array();
            foreach ($rs as $data) {
                $instance = new \grade_item();
                \grade_object::set_properties($instance, $data);
                $courseitems[$instance->id] = $instance;
            }
            $rs->close();
        }

        // Determine if any courses were missing.
        $receivedids = array();
        foreach ($courseitems as $item) {
            $receivedids[] = $item->courseid;
        }
        $missingids = array_diff($courseids, $receivedids);

        // Create grade items for any courses that didn't have one.
        if (!empty($missingids)) {
            foreach ($missingids as $courseid) {
                // First get category - it creates the associated grade item.
                $coursecategory = \grade_category::fetch_course_category($courseid);
                $gradeitem = $coursecategory->get_grade_item();
                $courseitems[$gradeitem->id] = $gradeitem;
            }
        }
        return $courseitems;
    }

    /**
     * Get an array of moodle courses we would create ELIS class enrolments for, if they did not exist.
     *
     * This returns a list of moodle course ids which are only linked to one ELIS class.
     *
     * @param array $courseids If supplied, limit results to these course ids.
     * @return array Array of moodle courses we would create ELIS class enrolments for, if they did not exist.
     */
    public function get_courses_to_create_enrolments($courseids = []) {
        global $DB;

        $courseidssql = '';
        $courseidsparams = [];
        if (!empty($courseids)) {
            list($courseidssql, $courseidsparams) = $DB->get_in_or_equal($courseids);
            $courseidssql = ' AND moodlecourseid '.$courseidssql;
        }

        $sql = 'SELECT moodlecourseid, count(moodlecourseid) as numentries
                  FROM {'.\classmoodlecourse::TABLE.'}
                 WHERE moodlecourseid > 0 '.$courseidssql.'
              GROUP BY moodlecourseid';
        $recs = $DB->get_recordset_sql($sql, $courseidsparams);
        $doenrol = [];
        foreach ($recs as $rec) {
            if ($rec->numentries == 1) {
                $doenrol[$rec->moodlecourseid] = $rec->moodlecourseid;
            }
        }
        return $doenrol;
    }

    /**
     * Create an enrolment record for an ELIS user into an ELIS class.
     *
     * @param int $pmuserid The ELIS userid.
     * @param int $muserid The Moodle userid.
     * @param int $mcourseid The Moodle courseid.
     * @param int $pmclassid The ELIS classid.
     * @param int $timenow (Optional) The time to use as a fallback enrolment time if the enrolment time cannot be determined.
     * @return \stdClass The created student object.
     */
    public function create_enrolment_record($pmuserid, $muserid, $mcourseid, $pmclassid, $timenow = null) {
        global $DB;

        if (empty($timenow)) {
            $timenow = time();
        }

        $sturec = new \stdClass;
        $sturec->classid = $pmclassid;
        $sturec->userid = $pmuserid;

        // Enrolment time will be the earliest found role assignment for this user.
        $enroltime = $timenow;
        $enrolments = $DB->get_recordset('enrol', array('courseid' => $mcourseid));
        foreach ($enrolments as $enrolment) {
            $etime = $DB->get_field('user_enrolments', 'timestart', array('enrolid' => $enrolment->id, 'userid'  => $muserid));
            if (!empty($etime) && $etime < $enroltime) {
                $enroltime = $etime;
            }
        }
        unset($enrolments);

        $sturec->enrolmenttime = $enroltime;
        $sturec->completetime = 0;
        $sturec->endtime = 0;
        $sturec->completestatusid = \student::STUSTATUS_NOTCOMPLETE;
        $sturec->grade = 0;
        $sturec->credits = 0;
        $sturec->locked = 0;
        $stuobj = new \student($sturec);
        try {
            $stuobj->save();
            $sturec->id = $stuobj->id;
        } catch (\pmclass_enrolment_limit_validation_exception $pme) {
            $waitlist = new \waitlist(array('classid' => $pmclassid, 'userid' => $pmuserid));
            $waitlist->save();
        } catch (\Exception $e) { // Other exceptions like pre-reqs not met, ...
            global $CFG;
            require_once($CFG->dirroot.'/local/elisprogram/lib/lib.php');
            if (in_cron()) {
                mtrace("ELIS grade-sync exception enrolling student {$pmuserid} in class {$pmclassid} - ".$e->getMessage());
            }
        }
        return $sturec;
    }

    /**
     * Get grade items and completion elements for elis and moodle courses that are linked together.
     *
     * @param array $courseids Array of course IDs to limit results to. If empty, does not limit.
     * @return array An array of grade items (index 0) and elis completion elements (index 1).
     *               Each are arrays of grade item/course completions, sorted by moodle course id, and pm course id.
     */
    public function get_grade_and_completion_elements($courseids = []) {
        global $DB;

        $courseidssql = '';
        $courseidsparams = [];
        if (!empty($courseids)) {
            list($courseidssql, $courseidsparams) = $DB->get_in_or_equal($courseids);
            $courseidssql = ' AND cmc.moodlecourseid '.$courseidssql;
        }

        $sql = 'SELECT cmp.id as cmpid,
                       cmp.courseid AS pmcourseid,
                       cmp.completion_grade cmpcompletiongrade,
                       cmc.moodlecourseid AS moodlecourseid,
                       gi.id AS giid,
                       gi.itemtype AS giitemtype,
                       gi.grademax AS gigrademax
                  FROM {'.\coursecompletion::TABLE.'} cmp
                  JOIN {'.\pmclass::TABLE.'} cls ON cls.courseid = cmp.courseid
                  JOIN {'.\classmoodlecourse::TABLE.'} cmc ON cmc.classid = cls.id
                       AND cmc.moodlecourseid > 0 '.$courseidssql.'
             LEFT JOIN {course_modules} crsmod ON crsmod.idnumber = cmp.idnumber AND crsmod.course = cmc.moodlecourseid
             LEFT JOIN {grade_items} gi
                           ON gi.courseid = cmc.moodlecourseid
                           AND (gi.idnumber = cmp.idnumber OR gi.idnumber = crsmod.id)';
        $data = $DB->get_recordset_sql($sql, $courseidsparams);
        $gis = array();
        $linkedcompelems = array();
        $compelems = array();
        foreach ($data as $rec) {
            if (!empty($rec->giid)) {
                $gis[$rec->moodlecourseid][$rec->giid] = (object)array(
                    'id' => $rec->giid,
                    'itemtype' => $rec->giitemtype,
                    'grademax' => $rec->gigrademax
                );
                $linkedcompelems[$rec->pmcourseid][$rec->giid] = (object)array(
                    'id' => $rec->cmpid,
                    'completion_grade' => $rec->cmpcompletiongrade
                );
            }

            $compelems[$rec->pmcourseid][$rec->cmpid] = (object)array(
                'id' => $rec->cmpid,
            );
        }

        return array($gis, $linkedcompelems, $compelems);
    }

    /**
     * Sync the course grade from Moodle to ELIS.
     *
     * @param object $sturec The ELIS student record.
     * @param grade_item $coursegradeitem The Moodle course grade_item object.
     * @param grade_grade $coursegradegrade The Moodle user's grade data for the course grade item.
     * @param bool $hascompelements true if item has completion elements, false otherwise.
     * @param int $completiongrade The completion grade for this course.
     * @param int $credits The number of credits for this course.
     * @param int $timenow The time to set the student complete time to if they are passed and don't have one set already.
     */
    public function sync_coursegrade($sturec, $coursegradeitem, $coursegradegrade, $hascompelements, $completiongrade, $credits,
            $timenow) {
        global $DB;

        if (isset($sturec->id) && !$sturec->locked && $coursegradegrade->finalgrade !== null) {
            // Clone of student record, to see if we actually change anything.
            $oldsturec = clone($sturec);

            $sturec->grade = $this->get_scaled_grade($coursegradegrade, $coursegradeitem->grademax);

            // Update completion status if all that is required is a course grade.
            // We could possibly add check for non-locked completion elements:
            // Eg. if ((($hascompelement === false ||
            //             !\student_grade::count([ new \field_filter('classid', $sturec->classid), new
            //             \field_filter('userid', $sturec->userid, new \field_filter('locked', 0)])) && ...
            if ($hascompelements === false && $sturec->grade >= $completiongrade) {
                $sturec->completetime = $coursegradegrade->get_dategraded();
                $sturec->completestatusid = \student::STUSTATUS_PASSED;
                $sturec->credits = floatval($credits);
                $sturec->locked = 1;
                grade_grade::gradesync_debug_out('No compelements, using course item dategraded: '.userdate($sturec->completetime));
            } else {
                $sturec->completetime = 0;
                $sturec->completestatusid = \student::STUSTATUS_NOTCOMPLETE;
                $sturec->credits = 0;
            }

            // Only update if we actually changed anything.
            // (exception: if the completetime gets smaller, it's probably because $coursegradegrade->get_dategraded()
            // returned an empty value, so ignore that change).
            if ($oldsturec->grade != $sturec->grade
                || $oldsturec->completetime < $sturec->completetime
                || $oldsturec->completestatusid != $sturec->completestatusid
                || $oldsturec->credits != $sturec->credits) {
                $stuobj = new \student($sturec);
                if ($sturec->completestatusid == \student::STUSTATUS_PASSED && empty($sturec->completetime)) {
                    // Make sure we have a valid complete time, if we passed.
                    $maxtimegraded = $DB->get_field(\student_grade::TABLE, 'MAX(timegraded)', ['classid' => $sturec->classid,
                        'userid' => $sturec->userid]);
                    if (empty($maxtimegraded)) {
                        // Check for Moodle course_completion record.
                        $mdlcourseid = false;
                        $mdlcourses = $stuobj->pmclass->classmoodle;
                        if ($mdlcourses && $mdlcourses->valid()) {
                            foreach ($mdlcourses as $mdlcourse) {
                                $mdlcourseid = $mdlcourse->moodlecourseid;
                                break;
                            }
                        }
                        if ($mdlcourseid && \classmoodlecourse::count(new \field_filter('moodlecourseid', $mdlcourseid)) == 1 &&
                                ($muserid = $DB->get_field(\usermoodle::TABLE, 'muserid', ['cuserid' => $sturec->userid]))) {
                            $maxtimegraded = $DB->get_field('course_completions', 'timecompleted',
                                    ['course' => $mdlcourseid, 'userid' => $muserid]);
                            if (!empty($maxtimegraded)) {
                                grade_grade::gradesync_debug_out('Got course_completion timecompleted: '.userdate($maxtimegraded));
                            }
                        }
                        if (empty($maxtimegraded)) {
                            $maxtimegraded = $timenow;
                            grade_grade::gradesync_debug_out('No completion, using timenow.');
                        }
                    } else {
                        grade_grade::gradesync_debug_out('Got LO MAX(timegraded): '.userdate($maxtimegraded));
                    }
                    $stuobj->completetime = $maxtimegraded;
                }
                try {
                    $stuobj->save();
                } catch (\Exception $e) { // Enrolment rec exists - this shouldn't happen ...
                    global $CFG;
                    require_once($CFG->dirroot.'/local/elisprogram/lib/lib.php');
                    if (in_cron()) {
                        mtrace("ELIS grade-sync exception saving student {$sturec->userid} grades for class {$sturec->classid} - ".$e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Convert a grade_grade's finalgrade into a percent based on the associated grade_item's maxgrade.
     *
     * @param grade_grade $gradegrade The grade_grade object.
     * @param int $grademax The maximum grade for the grade_item.
     * @return float The resulting percent grade.
     */
    public function get_scaled_grade(grade_grade $gradegrade, $grademax) {
        // Ignore mingrade for now... Don't really know what to do with it.
        if ($gradegrade->finalgrade >= $grademax) {
            $gradepercent = 100;
        } else if ($gradegrade->finalgrade <= 0) {
            $gradepercent = 0;
        } else {
            $gradepercent = (($gradegrade->finalgrade / $grademax) * 100.0);
        }
        return $gradepercent;
    }

    /**
     * Sync moodle non-course grade_items to ELIS coursecompletion elements.
     *
     * @param object $causer The current user information being processed (w/ associated info). (@see get_syncable_users)
     * @param array $gis Array of moodle grade_item information. (@see get_grade_and_completion_elements)
     * @param array $compelements Array of ELIS coursecompletion information. (@see get_grade_and_completion_elements)
     * @param array $moodlegrades Array of grade_grade objects, indexed by associated grade_item id.
     * @param array $cmgrades Array of ELIS student_grade information.
     * @param int $timenow The current time.
     */
    public function sync_completionelements($causer, $gis, $compelements, $moodlegrades, $cmgrades, $timenow) {
        global $DB;

        foreach ($compelements as $giid => $coursecompletion) {
            if (!isset($moodlegrades[$giid]) || !isset($moodlegrades[$giid]->finalgrade) || !isset($gis[$giid])) {
                continue;
            }
            // Calculate Moodle grade as a percentage.
            $gradeitemgrade = $moodlegrades[$giid];
            $gradepercent = $this->get_scaled_grade($gradeitemgrade, $gis[$giid]->grademax);

            if (isset($cmgrades[$coursecompletion->id])) {
                // Update existing completion element grade.
                $studentgrade = $cmgrades[$coursecompletion->id];

                $mgradeiscat = ($gis[$giid]->itemtype === 'category') ? true : false;
                $mgradeisnewer = ($gradeitemgrade->get_dategraded() > $studentgrade->timegraded) ? true : false;

                if (!$studentgrade->locked && ($mgradeisnewer === true || $mgradeiscat === true)) {

                    // Clone of record, to see if we actually change anything.
                    $oldgrade = clone($studentgrade);

                    $studentgrade->grade = $gradepercent;
                    $studentgrade->timegraded = $gradeitemgrade->get_dategraded();
                    // If completed, lock it.
                    $studentgrade->locked = ($studentgrade->grade >= $coursecompletion->completion_grade) ? 1 : 0;

                    // Only update if we actually changed anything.
                    if ($oldgrade->grade != $studentgrade->grade
                        || $oldgrade->timegraded != $studentgrade->timegraded
                        || $oldgrade->grade != $studentgrade->grade
                        || $oldgrade->locked != $studentgrade->locked) {

                        $studentgrade->timemodified = $timenow;
                        $DB->update_record(\student_grade::TABLE, $studentgrade);
                    }
                }
            } else {
                // No completion element grade exists: create a new one.
                $studentgrade = new \stdClass;
                $studentgrade->classid = $causer->pmclassid;
                $studentgrade->userid = $causer->cmid;
                $studentgrade->completionid = $coursecompletion->id;
                $studentgrade->grade = $gradepercent;
                $studentgrade->timegraded = $gradeitemgrade->get_dategraded();
                $studentgrade->timemodified = $timenow;
                // If completed, lock it.
                $studentgrade->locked = ($studentgrade->grade >= $coursecompletion->completion_grade) ? 1 : 0;
                $DB->insert_record(\student_grade::TABLE, $studentgrade);
            }
        }
    }

    /**
     * Get grade_grade objects for a user in moodle course.
     *
     * @param int $muserid The moodle user ID.
     * @param int $moodlecourseid The moodle course ID.
     * @param array $gis Array of grade_items to get grade data for.
     * @return array Array of grade_grade objects, indexed by associated grade_item id.
     */
    public function get_moodlegrades($muserid, $moodlecourseid, $gis) {
        global $DB, $CFG;

        if (empty($gis)) {
            return array();
        }

        $graderecords = array();
        $params = array();

        list($gisql, $giparams) = $DB->get_in_or_equal(array_keys($gis), SQL_PARAMS_NAMED, 'items');

        $gradessql = 'SELECT *
                        FROM {grade_grades} grade
                       WHERE userid = :muserid AND itemid '.$gisql.'
                    ORDER BY itemid ASC';
        $params = array_merge(array('muserid' => $muserid), $giparams);
        $graderecordstmp = $DB->get_recordset_sql($gradessql, $params);
        $graderecords = array();
        foreach ($graderecordstmp as $i => $record) {
            $graderecords[$record->itemid] = $record;
        }
        unset($graderecordstmp);

        $grades = array();
        foreach ($gis as $gradeitem) {
            if (isset($graderecords[$gradeitem->id])) {
                $grades[$gradeitem->id] = new grade_grade($graderecords[$gradeitem->id], false);
            } else {
                $grades[$gradeitem->id] = new grade_grade(['userid' => $muserid, 'itemid' => $gradeitem->id], false);
            }
        }
        return $grades;
    }

    /**
     * Synchronize users from Moodle to ELIS.
     *
     * @param int $requestedmuserid A moodle userid to sync. If 0, syncs all available.
     */
    public function synchronize_moodle_class_grades($requestedmuserid = 0) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/grade/lib.php');
        require_once(\elispm::lib('data/classmoodlecourse.class.php'));

        set_time_limit(0);
        $start = microtime(true);
        $timenow = time();
        $maxtime = 0;

        $lastrun = null;
        $useincgsync = false;

        grade_grade::init_elis_options($timenow);

        // We only use incremental sync when running for all users. Otherwise running for a single user would record
        // a new lastrun time and prevent other users from syncing.
        if (empty($requestedmuserid)) {
            $useincgsync = get_config('local_elisprogram', 'incrementalgradesync');
            $useincgsync = (!empty($useincgsync)) ? true : false;
            if ($useincgsync === true) {
                $maxsyncsecs = get_config('local_elisprogram', 'incrementalgradesync_maxsyncsecs');
                $maxsyncsecs = !empty($maxsyncsecs) ? $maxsyncsecs : DEFAULT_SYNC_SECS;
                $maxtime = $timenow + $maxsyncsecs;
                $lastrun = get_config('local_elisprogram', 'incrementalgradesync_lastrun');
                $origlastrun = $lastrun = (!empty($lastrun)) ? (int)$lastrun : 0;
                $nextuser = get_config('local_elisprogram', 'incrementalgradesync_nextuser');
                $syncstartuser = !empty($nextuser) ? (int)$nextuser : 0;
                if ($syncstartuser) {
                    $prevrun = get_config('local_elisprogram', 'incrementalgradesync_prevrun');
                    $prevrun = !empty($prevrun) ? (int)$prevrun : 0;
                    $lastrun = $prevrun;
                }
            }
        }

        if ($useincgsync === true) {
            if (in_cron()) {
                mtrace('Using incremental grade sync.');
                mtrace('Syncing grades and enrolments newer than '.date('r', $lastrun));
            }
        }

        $syncableuserids = null;
        if (!empty($requestedmuserid)) {
            $syncableuserids = [$requestedmuserid];
        } else {
            if ($useincgsync === true) {
                $maxuserchunk = get_config('local_elisprogram', 'incrementalgradesync_maxuserchunk');
                $limitnum = !empty($maxuserchunk) ? $maxuserchunk : DEFAULT_USER_CHUNK;

                // Get a list of users that have at least one updated record since our last sync.
                $sql = 'SELECT DISTINCT userid FROM {grade_grades} WHERE timemodified >= ?';
                $params = [$lastrun];
                if ($origlastrun != $lastrun) {
                    $sql .= ' AND timemodified <= ?';
                    $params[] = $origlastrun;
                }
                $sql .= ' ORDER BY id ASC';
                $newgradeuserids = $DB->get_records_sql($sql, $params);
                $newgradeuserids = array_keys($newgradeuserids);

                $sql = 'SELECT DISTINCT userid FROM {user_enrolments} WHERE timecreated >= ?';
                if ($origlastrun != $lastrun) {
                    $sql .= ' AND timecreated <= ?';
                }
                $sql .= ' ORDER BY id ASC';
                $newenrolmentuserids = $DB->get_records_sql($sql, $params);
                $newenrolmentuserids = array_keys($newenrolmentuserids);
                $syncableuserids = array_unique(array_merge($newgradeuserids, $newenrolmentuserids));
                $nextsyncuser = 0;
                if ($syncstartuser || count($syncableuserids) > $limitnum) {
                    $limitfrom = 0;
                    if ($syncstartuser) {
                        if (($limitfrom = array_search($syncstartuser, $syncableuserids)) === false) {
                            $limitfrom = 0;
                        }
                    }
                    $syncableuserids = array_slice($syncableuserids, $limitfrom);
                    if (count($syncableuserids) > $limitnum) {
                        $nextsyncuser = $syncableuserids[$limitnum];
                        $syncableuserids = array_slice($syncableuserids, 0, $limitnum);
                    }
                }
            }
        }

        $syncablecourseids = [];
        if ($useincgsync === true) {
            $sql = 'SELECT DISTINCT gi.courseid
                               FROM {grade_grades} gg
                               JOIN {grade_items} gi ON gi.id = gg.itemid
                              WHERE gg.timemodified >= ?';
            $params = [$lastrun];
            if ($origlastrun != $lastrun) {
                $sql .= ' AND gg.timemodified <= ?';
                $params[] = $origlastrun;
            }
            $sql .= ' ORDER BY gg.id ASC';
            $newgradecourseids = $DB->get_records_sql($sql, $params);
            $newgradecourseids = array_keys($newgradecourseids);

            $sql = 'SELECT DISTINCT e.courseid
                               FROM {user_enrolments} ue
                               JOIN {enrol} e ON e.id = ue.enrolid
                              WHERE ue.timecreated >= ?';
            if ($origlastrun != $lastrun) {
                $sql .= ' AND ue.timecreated <= ?';
            }
            $sql .= ' ORDER BY ue.id ASC';
            $newenrolmentcourseids = $DB->get_records_sql($sql, $params);
            $newenrolmentcourseids = array_keys($newenrolmentcourseids);
            $syncablecourseids = array_unique(array_merge($newgradecourseids, $newenrolmentcourseids));
        }

        $causers = $this->get_syncable_users($syncableuserids, $syncablecourseids);
        if (empty($causers)) {
            if ($useincgsync === true) {
                set_config('incrementalgradesync_nextuser', $nextsyncuser, 'local_elisprogram');
                if (!$nextsyncuser) {
                    if (in_cron()) {
                        mtrace('No new data.');
                    }
                    set_config('incrementalgradesync_prevrun', $origlastrun, 'local_elisprogram');
                }
                if (!$syncstartuser) {
                    set_config('incrementalgradesync_lastrun', $timenow, 'local_elisprogram');
                }
            }
            return false;
        }

        // Get moodle course ids.
        if (!empty($syncablecourseids)) {
            list($mcourseidselect, $mcourseidparams) = $DB->get_in_or_equal($syncablecourseids);
            $mcourseidselect = 'moodlecourseid '.$mcourseidselect;
            $moodlecourseidsorig = $DB->get_recordset_select(\classmoodlecourse::TABLE, $mcourseidselect, $mcourseidparams);
        } else {
            $moodlecourseidsorig = $DB->get_recordset_select(\classmoodlecourse::TABLE, 'moodlecourseid > 0');
        }
        $moodlecourseids = array();
        foreach ($moodlecourseidsorig as $i => $rec) {
            if (empty($requestedmuserid) || is_enrolled(\context_course::instance($rec->moodlecourseid), $requestedmuserid)) {
                $moodlecourseids[] = $rec->moodlecourseid;
            }
        }
        unset($moodlecourseidsorig);

        // Regrade each course.
        foreach ($moodlecourseids as $moodlecourseid) {
            grade_regrade_final_grades($moodlecourseid);
        }

        // Get course grade items and index by courseid.
        $coursegradeitemsorig = static::fetch_course_items($moodlecourseids);
        $coursegradeitems = array();
        foreach ($coursegradeitemsorig as $i => $item) {
            $coursegradeitems[$item->courseid] = $item;
            unset($coursegradeitemsorig[$i]);
        }

        $doenrol = $this->get_courses_to_create_enrolments($moodlecourseids);

        list($allgis, $alllinkedcompelements, $allcompelements) = $this->get_grade_and_completion_elements($moodlecourseids);
        $ecrs = [];
        $lastuser = 0;
        $cnt = 0;
        foreach ($causers as $causer) {
            if ($maxtime && $lastuser != $causer->muid) {
                // Finished syncing a user, check for over time limit.
                ++$cnt;
                if ($lastuser && time() > $maxtime) {
                    if (in_cron()) {
                        mtrace("Over time limit - exiting.");
                    }
                    $nextsyncuser = $causer->muid;
                    break;
                }
                $lastuser = $causer->muid;
            }

            $gis = (isset($allgis[$causer->moodlecourseid])) ? $allgis[$causer->moodlecourseid] : array();
            $linkedcompelements = (isset($alllinkedcompelements[$causer->pmcourseid])) ? $alllinkedcompelements[$causer->pmcourseid] : array();

            $compelements = (isset($allcompelements[$causer->pmcourseid])) ? $allcompelements[$causer->pmcourseid] : array();

            if (!isset($coursegradeitems[$causer->moodlecourseid])) {
                continue;
            }

            $coursegradeitem = $coursegradeitems[$causer->moodlecourseid];
            $gis[$coursegradeitem->id] = $coursegradeitem;

            if ($coursegradeitem->grademax == 0) {
                // No maximum course grade, so we can't calculate the student's grade.
                continue;
            }

            // If no enrolment record in ELIS, let's set one.
            if (empty($causer->id)) {
                if (!isset($doenrol[$causer->moodlecourseid])) {
                    continue;
                }
                $sturec = $this->create_enrolment_record($causer->cmid, $causer->muid, $causer->moodlecourseid, $causer->pmclassid, $timenow);
                if (empty($sturec->id)) {
                    // TBD: Error occurred creating student record.
                    continue;
                }
                $sturec = (array)$sturec;

                // Merge the new student record with $causer.
                foreach ($sturec as $k => $v) {
                    $causer->$k = $v;
                }
            }

            $moodlegrades = $this->get_moodlegrades($causer->muid, $causer->moodlecourseid, $gis);

            // Handle the course grade.
            if (isset($moodlegrades[$coursegradeitem->id])) {
                if (!isset($ecrs[$causer->pmcourseid])) { // Cache as required.
                    if (($credits = $DB->get_field(\course::TABLE, 'credits', ['id' => $causer->pmcourseid])) === false) {
                        // Orphaned class.
                        continue;
                    }
                    $ecrs[$causer->pmcourseid] = new \stdClass;
                    $ecrs[$causer->pmcourseid]->pmcoursecredits = $credits;
                    $ecrs[$causer->pmcourseid]->pmcoursecompletiongrade = $DB->get_field(\course::TABLE, 'completion_grade',
                            ['id' => $causer->pmcourseid]);
                }
                $coursehascompelements = (!empty($compelements)) ? true : false;
                $this->sync_coursegrade($causer, $coursegradeitem, $moodlegrades[$coursegradeitem->id], $coursehascompelements,
                        $ecrs[$causer->pmcourseid]->pmcoursecompletiongrade, $ecrs[$causer->pmcourseid]->pmcoursecredits, $timenow);
            }

            // Handle completion elements.
            $cmgrades = $this->get_elis_coursecompletion_grades($requestedmuserid, $causer->muid, $causer->pmclassid);
            $this->sync_completionelements($causer, $gis, $linkedcompelements, $moodlegrades, $cmgrades, $timenow);
        }

        if ($this->completionelementrecset !== null) {
            $this->completionelementrecset->close();
        }
        $end = microtime(true);

        if ($useincgsync === true) {
            if (in_cron()) {
                mtrace("{$cnt} user(s) processed.");
            }
            set_config('incrementalgradesync_nextuser', $nextsyncuser, 'local_elisprogram');
            if (!$syncstartuser) {
                set_config('incrementalgradesync_lastrun', $timenow, 'local_elisprogram');
            }
            if (!$nextsyncuser) {
                set_config('incrementalgradesync_prevrun', $origlastrun, 'local_elisprogram');
            }
            if (time() > $maxtime) {
                $limitnum = $cnt;
            } else if ($cnt >= ($limitnum - 1) && ($maxtime - time()) >= MIN_AUTOCHUNK_SECS) {
                $limitnum *= 2;
            }
            if ($limitnum < MIN_USER_CHUNK) {
                $limitnum = MIN_USER_CHUNK;
            } else if ($limitnum > MAX_USER_CHUNK) {
                $limitnum = MAX_USER_CHUNK;
            }
            set_config('incrementalgradesync_maxuserchunk', $limitnum, 'local_elisprogram');
        }
        // error_log("synchronize.php::synchronize_moodle_class_grades({$requestedmuserid}) > EXIT!");
    }
}
