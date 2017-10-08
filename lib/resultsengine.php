<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once dirname(__FILE__).'/setup.php';
require_once elispm::lib('data/usertrack.class.php');
require_once elis::lib('data/customfield.class.php');


define('RESULTS_ENGINE_LANG_FILE', 'local_elisprogram');

// max out at 2 minutes (= 120 seconds)
define('RESULTS_ENGINE_USERACT_TIME_LIMIT', 120);

define('RESULTS_ENGINE_GRADE_SET', 1);
define('RESULTS_ENGINE_SCHEDULED', 2);
define('RESULTS_ENGINE_MANUAL',    3);

define('RESULTS_ENGINE_AFTER_START', 1);
define('RESULTS_ENGINE_BEFORE_END',  2);
define('RESULTS_ENGINE_AFTER_END',   3);

define('RESULTS_ENGINE_ASSIGN_TRACK',   0);
define('RESULTS_ENGINE_ASSIGN_CLASS',   1);
define('RESULTS_ENGINE_UPDATE_PROFILE', 2);

/**
 * Check if class results are ready to be processed and if so process them
 */
function results_engine_cron() {
    $rununtil = time() + RESULTS_ENGINE_USERACT_TIME_LIMIT;
    $cnt = 0;
    $tot = 0;
    $tout = false;
    $activetypes = [
            ['triggertype' => RESULTS_ENGINE_SCHEDULED, 'context' => CONTEXT_ELIS_CLASS],
            ['triggertype' => RESULTS_ENGINE_SCHEDULED, 'context' => CONTEXT_ELIS_COURSE],
            ['triggertype' => RESULTS_ENGINE_GRADE_SET, 'context' => CONTEXT_ELIS_CLASS],
            ['triggertype' => RESULTS_ENGINE_GRADE_SET, 'context' => CONTEXT_ELIS_COURSE]
    ];
    $randkeys = array_rand($activetypes, 4);
    foreach ($randkeys as $randkey) {
        $activetype = $activetypes[$randkey];
        $actives = results_engine_get_active($activetype['triggertype'], $activetype['context']);
        foreach ($actives as $active) {
            ++$tot;
            $active = results_engine_check($active);
            if ($active->proceed) {
                $active->cron = true;
                results_engine_process($active);
                ++$cnt;
            }

            if (time() > $rununtil) {
                mtrace("... timeout: {$cnt} of {$tot} processed.");
                $tout = true;
                break 2;
            }
        }
    }
    if (!$tout && $tot) {
        mtrace("... complete: {$cnt} of {$tot} processed.");
    }
    unset($actives);
}

/**
 * Process an entire class manually
 *
 * @param object $context The context object for the course/class to process
 * @return boolean Success/failure
 * @uses $DB;
 */
function results_engine_manual($context) {
    global $DB;

    $processed = false;

    $level       = CONTEXT_ELIS_CLASS;
    $courselevel = CONTEXT_ELIS_COURSE;
    $tables = '{local_elisprogram_cls} cc'
            .' JOIN {context} c ON c.instanceid=cc.id AND c.contextlevel=?'
            .' JOIN {local_elisprogram_res} cr ON cr.contextid=c.id';
    $where = array('cc.id = ?');

    if ($context->contextlevel == $courselevel) {
        $level    = $courselevel;
        $tables = '{local_elisprogram_cls} cc'
                .' JOIN {local_elisprogram_crs} ccd ON ccd.id=cc.courseid'
                .' JOIN {context} c ON c.instanceid=ccd.id AND c.contextlevel=?'
                .' JOIN {local_elisprogram_res} cr ON cr.contextid=c.id';
        $where = array('ccd.id = ?');
    }

    $sql = 'SELECT cc.id, cc.idnumber, cc.startdate, cc.enddate, cr.eventtriggertype,'
         .       ' cr.criteriatype, cr.id as engineid, cr.triggerstartdate, cr.days'
         .' FROM '. $tables
         .' WHERE '. implode(' AND ', $where);

    $params = array($level, $context->instanceid);
    $classes = $DB->get_recordset_sql($sql, $params);

    foreach ($classes as $class) {
        $class->cron = false;
        $class->rundate = time();
        $class->scheduleddate = 0;

        results_engine_process($class);
        $processed = true;
    }
    unset($classes);
    return $processed;
}

/**
 * Check if this class is ready to be processed
 *
 * Class properties:
 *   id               - id of class
 *   startdate        - startdate of class (0 means unset)
 *   enddate          - enddate of class (0 means unset)
 *   triggerstartdate - the type of start date trigger used (see defines in results_engine plugin)
 *   days             - number of days to offset triggerstartdate
 *
 * @param object $class An object with the important class properties
 * @return bool Whether the class is ready to be processed
 */
function results_engine_check($class) {
    $class->proceed = false;
    $class->rundate = time();

    $offset = $class->days * 86400;

    // We always have to check individual students when the trigger is "after grade set"
    if ($class->eventtriggertype == RESULTS_ENGINE_GRADE_SET) {
        $class->proceed = true;
        $class->scheduleddate = 0;
        return $class;
    }

    if ($class->triggerstartdate == RESULTS_ENGINE_AFTER_START) {
        if ($class->startdate <= 0) {
            if (isset($class->cron) && $class->cron) {
                print(get_string('results_no_start_date_set', RESULTS_ENGINE_LANG_FILE, $class) ."\n");
            }
            return $class;
        }
        $class->scheduleddate = $class->startdate + $offset;
    } else {
        if ($class->enddate <= 0) {
            if (isset($class->cron) && $class->cron) {
                print(get_string('results_no_end_date_set', RESULTS_ENGINE_LANG_FILE, $class) ."\n");
            }
            return $class;
        }

        if ($class->triggerstartdate == RESULTS_ENGINE_BEFORE_END) {
            $offset = -$offset;
        }
        $class->scheduleddate = $class->enddate + $offset;
    }
    if ($class->rundate > $class->scheduleddate) {
        $class->proceed = true;
    }

    return $class;
}

/**
 * Get the results engines that are active
 *
 * Properties of returned objects:
 *   id               - id of class
 *   startdate        - startdate of class (0 means unset)
 *   enddate          - enddate of class (0 means unset)
 *   triggerstartdate - the type of start date trigger used (see defines in results_engine plugin)
 *   days             - number of days to offset triggerstartdate
 *   criteriatype     - what mark to look at, 0 for final mark, anything else is an element id
 *
 * @param int $triggertype Either RESULTS_ENGINE_SCHEDULED or RESULTS_ENGINE_GRADE_SET
 * @param int $context Either CONTEXT_ELIS_COURSE or CONTEXT_ELIS_CLASS
 * @return array A recordset of class objects
 * @uses $DB
 */
function results_engine_get_active($triggertype, $context) {
    global $DB;

    $courselevel = CONTEXT_ELIS_COURSE;
    $classlevel  = CONTEXT_ELIS_CLASS;

    $fields = ['cls.id', 'cls.idnumber', 'cls.startdate', 'cls.enddate',  'cr.id AS engineid', 'cr.eventtriggertype',
            'cr.triggerstartdate', 'cr.criteriatype', 'cr.lockedgrade', 'cr.days'];
    $conditions = [
            'cr.active = 1',
            'cr.eventtriggertype = '.$triggertype
    ];
    if ($triggertype == RESULTS_ENGINE_SCHEDULED) {
        $conditions[] = 'NOT EXISTS (SELECT \'x\'
                                       FROM {local_elisprogram_res_clslog} crcl
                                      WHERE crcl.classid = cls.id
                                            AND crcl.daterun IS NOT NULL)';
    }
    if ($context == CONTEXT_ELIS_COURSE) {
        $conditions[] = "NOT EXISTS (SELECT 'x'
                                      FROM {context} c2
                                      JOIN {local_elisprogram_res} cr2 ON cr2.contextid = c2.id
                                           AND cr2.active = 1
                                     WHERE c2.instanceid = cls.id
                                           AND c2.contextlevel = {$classlevel})";
        // Get course level instances that have not been overriden or already run.
        $sql = 'SELECT '.implode(', ', $fields).'
                  FROM {local_elisprogram_res} cr
                  JOIN {context} c ON c.id = cr.contextid AND c.contextlevel = ?
                  JOIN {local_elisprogram_crs} cou ON cou.id = c.instanceid
                  JOIN {local_elisprogram_cls} cls ON cls.courseid = cou.id
                 WHERE '.implode(' AND ', $conditions);
        $params = [$courselevel];
    } else { // CONTEXT_ELIS_CLASS
        // Get pmclass level instances that have not already run.
        $sql = 'SELECT '.implode(', ', $fields).'
                  FROM {local_elisprogram_res} cr
                  JOIN {context} c ON c.id = cr.contextid AND c.contextlevel = ?
                  JOIN {local_elisprogram_cls} cls ON cls.id = c.instanceid
                 WHERE '.implode(' AND ', $conditions);
        $params = [$classlevel];
    }
    return $DB->get_recordset_sql($sql, $params);
}

/**
 * Get the list of students for this class
 *
 * Class properties:
 *   id               - id of class
 *   criteriatype     - what mark to look at, 0 for final mark, anything else is an element id
 *   eventtriggertype - what type of trigger the engine uses
 *   lockedgrade      - whether the grade must be locked if "set grade" trigger is used
 *
 * If the trigger is set to "set grade" only return students with recently set grades.
 *
 * @param object $class The class object
 * @return array An array of student objects with id and grade
 * @uses $DB;
 */
function results_engine_get_students($class) {
    global $DB;
    $params = array('classid' => $class->id);
    $fields = array('id', 'userid', 'grade', 'locked');
    $table  = 'local_elisprogram_cls_enrol';

    if ($class->criteriatype > 0) {
        $table = 'local_elisprogram_cls_graded';
        $params['completionid'] = $class->criteriatype;
    }

    if ($class->eventtriggertype == RESULTS_ENGINE_GRADE_SET) {
        $criteria = array();

        foreach ($params as $param => $value) {
            $criteria[] = "g.$param = $value";
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = 'g.'. $value;
        }

        $sql = 'SELECT DISTINCT '.implode(',', $fields).'
                  FROM {'.$table.'} g
                 WHERE '.implode(' AND ', $criteria).'
                       AND NOT EXISTS (SELECT \'x\'
                                         FROM {local_elisprogram_res_clslog} c
                                         JOIN {local_elisprogram_res_stulog} l ON l.classlogid = c.id
                                        WHERE l.userid = g.userid
                                              AND c.classid = ?)';
        $students = $DB->get_records_sql($sql, [$class->id]);

        if ($class->lockedgrade) {
            foreach ($students as $key => $student) {
                if (! $student->locked) {
                    unset($students[$key]);
                }
            }
        }
    } else {
        $students = $DB->get_records($table, $params, '', implode(',', $fields));
    }

    return $students;
}

/**
 * Process all the students in this class
 *
 * Class properties required:
 *   id               - id of class
 *   criteriatype     - what mark to look at, 0 for final mark, anything else is an element id
 *   engineid         - id of results engine entry
 *   scheduleddate    - date when it was supposed to run
 *   rundate          - date when it is being run
 *
 * Class properties required by sub-functions:
 *   eventtriggertype - what type of trigger the engine uses
 *   lockedgrade     - whether the grade must be locked if "set grade" trigger is used
 *
 * @param $class object The class object see above for required attributes
 * @return boolean Success/failure
 * @uses $CFG
 */
function results_engine_process($class) {
    global $CFG, $DB;

    $params    = array('classid' => $class->id);

    $students  = results_engine_get_students($class);

    if (sizeof($students) == 0) {
        return true;
    }

    $params = array('resultsid' => $class->engineid);
    $fields = 'id, actiontype, minimum, maximum, trackid, classid, fieldid, fielddata';
    $actions = $DB->get_records('local_elisprogram_res_action', $params, '', $fields);

    $fieldids = array();
    $classids = array();
    $trackids = array();

    foreach ($actions as $action) {
        if ($action->actiontype == RESULTS_ENGINE_UPDATE_PROFILE) {
            $fieldids[$action->fieldid] = $action->fieldid;
        } else if ($action->actiontype == RESULTS_ENGINE_ASSIGN_CLASS) {
            $classids[$action->classid] = $action->classid;
        } else if ($action->actiontype == RESULTS_ENGINE_ASSIGN_TRACK) {
            $trackids[$action->trackid] = $action->trackid;
        }
    }

    foreach ($fieldids as $id) {
        if ($record = $DB->get_record('local_eliscore_field', array('id' => $id))) {
            $userfields[$id] = new field($record, null, array(), true);
        }
    }

    $classes = $DB->get_records_list('local_elisprogram_cls', 'id', $classids);
    $tracks  = $DB->get_records_list('local_elisprogram_trk', 'id', $trackids);

    // Log that the class has been processed
    $log = new stdClass();
    $log->classid       = $class->id;
    $log->datescheduled = $class->scheduleddate;
    $log->daterun       = $class->rundate;
    $classlogid = $DB->insert_record('local_elisprogram_res_clslog', $log);

    $log = new stdClass();
    $log->classlogid = $classlogid;
    $log->daterun    = $class->rundate;

    // Find the correct action to take based on student marks
    foreach ($students as $student) {
        $do = null;

        foreach ($actions as $action) {

            if (elis_float_comp($student->grade, $action->minimum, '>=') && elis_float_comp($student->grade, $action->maximum, '<=')) {
                $do = $action;
                break;
            }
        }

        if ($do != null) {
            $obj = new stdClass();

            switch($do->actiontype) {

                case RESULTS_ENGINE_ASSIGN_TRACK:
                    usertrack::enrol($student->userid, $do->trackid);
                    $message = 'results_action_assign_track';
                    $track = $tracks[$do->trackid];
                    $obj->name = $track->name .' ('. $track->idnumber .')';
                    break;

                case RESULTS_ENGINE_ASSIGN_CLASS:
                    $enrol = new student();
                    $enrol->classid = $do->classid;
                    $enrol->userid  = $student->userid;
                    $enrol->save();
                    $message = 'results_action_assign_class';
                    $obj->name = $classes[$do->classid]->idnumber;
                    break;

                case RESULTS_ENGINE_UPDATE_PROFILE:
                    if (! array_key_exists($do->fieldid, $userfields)) {
                        print(get_string('results_field_not_found', RESULTS_ENGINE_LANG_FILE, $do) ."\n");
                        break;
                    }

                    /*
                    $context = \local_elisprogram\context\user::instance($student->userid);
                    field_data::set_for_context_and_field($context, $userfields[$do->fieldid], $do->fielddata);
                    */

                    if (user::exists(new field_filter('id', $student->userid))) {
                        //get user
                        $user = new user($student->userid);

                        //set field
                        $field = 'field_'.$userfields[$do->fieldid]->shortname;
                        $user->$field = $do->fielddata;
                        $user->save();
                    }

                    $message = 'results_action_update_profile';
                    $obj->name  = $userfields[$do->fieldid]->shortname;
                    $obj->value = $do->fielddata;
                    break;

                default:
                    // If we don't know what we're doing, do nothing.
                    break;
            }
            $obj->id = $do->id;
            $log->action     = get_string($message, RESULTS_ENGINE_LANG_FILE, $obj);
            $log->userid     = $student->userid;
            $DB->insert_record('local_elisprogram_res_stulog', $log, false);
        }
    }

    if (isset($class->cron) && $class->cron) {
        print(get_string('results_class_processed', RESULTS_ENGINE_LANG_FILE, $class) ."\n");
    }
    return true;
}
