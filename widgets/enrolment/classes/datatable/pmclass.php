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
 * @package    eliswidget_enrolment
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

namespace eliswidget_enrolment\datatable;

/**
 * A datatable implementation for lists of classes within course.
 */
class pmclass extends base {
    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_cls';

    /** @var int The number of results displayed per page of the table. */
    const RESULTSPERPAGE = 5;

    /** @var int The ID of the course we're getting classes for. */
    protected $courseid = null;

    /** @var int The ID of the program we're getting classes for. Optional. */
    protected $programid = null;

    /**
     * Constructor.
     * @param \moodle_database $DB An active database connection.
     * @param string $ajaxendpoint The ajax endpoint URL.
     * @param int $courseid
     * @param int $programid
     */
    public function __construct(\moodle_database &$DB, $ajaxendpoint, $courseid = null, $programid = null) {
        $this->courseid = $courseid;
        $this->programid = $programid;
        parent::__construct($DB, $ajaxendpoint);
    }

    /**
     * Gets an array of available filters.
     *
     * @return array An array of \deepsight_filter objects that will be available.
     */
    public function get_filters() {
        global $DB, $CFG;

        require_once(\elispm::lib('deepsight/lib/filter.php'));
        require_once(\elispm::lib('deepsight/lib/filters/textsearch.filter.php'));
        require_once(\elispm::lib('deepsight/lib/filters/date.filter.php'));
        require_once(\elispm::lib('data/user.class.php'));

        $langidnumber = get_string('class_idnumber', 'local_elisprogram');
        $langstartdate = get_string('class_startdate', 'local_elisprogram');
        $langenddate = get_string('class_enddate', 'local_elisprogram');
        $langclassstatus = get_string('class_status', 'local_elisprogram');
        $euserid = \user::get_current_userid();
        $classstatusfilter = new \deepsight_filter_classstatus($this->DB, 'classstatus', $langclassstatus, array('userid' => $euserid, 'programid' => $this->programid),
                $CFG->wwwroot.'/local/elisprogram/widgets/enrolment/ajax.php'); // TBD.
        $classstatusfilter->set_default(''); // TBD.

        $filters = [
                new \deepsight_filter_textsearch($DB, 'idnumber', $langidnumber, ['element.idnumber' => $langidnumber]),
                new \deepsight_filter_date($DB, 'startdate', $langstartdate, ['element.startdate' => $langstartdate]),
                new \deepsight_filter_date($DB, 'enddate', $langenddate, ['element.enddate' => $langenddate]),
                $classstatusfilter
        ];

        // Add custom fields.
        $pmclassctxlevel = \local_eliscore\context\helper::get_level_from_name('class');
        $customfieldfilters = $this->get_custom_field_info($pmclassctxlevel, ['table' => get_called_class()]);
        $filters = array_merge($filters, $customfieldfilters);

        // Restrict to configured enabled fields.
        $enabledfields = get_config('eliswidget_enrolment', 'classenabledfields');
        if (!empty($enabledfields)) {
            $enabledfields .= ',';
        }
        $enabledfields .= 'classstatus'; // TBD: always add classstatus filter?
        if (!empty($enabledfields)) {
            $enabledfields = explode(',', $enabledfields);
            foreach ($filters as $i => $filter) {
                if (!in_array($filter->get_name(), $enabledfields)) {
                    unset($filters[$i]);
                }
            }
        }

        return $filters;
    }

    /**
     * Gets an array of fields that will always be selected, regardless of what has been enabled.
     *
     * @return array An array of fields that will always be selected.
     */
    public function get_fixed_select_fields() {
        return [
            'element.idnumber' => '',
            'element.maxstudents' => '',
            'enrol.id' => '',
            'enrol.completestatusid' => '',
            'enrol.grade' => '',
            'enrol.completetime' => '',
            'waitlist.id' => '',
        ];
    }

    /**
     * Get an array of datafields that will always be visible.
     *
     * @return array Array of filter aliases for fields that will always be visible.
     */
    public function get_fixed_visible_datafields() {
        return ['idnumber'];
    }

    /**
     * Get an array containing a list of visible and hidden datafields.
     *
     * For fields that are not fixed (see self::get_fixed_visible_datafields), additional fields are displayed when the user
     * searches on them. For fields that are not being searched on, they can be viewed by clicking a "more" link.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of field information, first item is visible fields, second is hidden fields.
     */
    public function get_datafields_by_visibility(array $filters = array()) {
       list($visible, $hidden) = parent::get_datafields_by_visibility($filters);
       $hidden['enrol_completetime'] = get_string('data_completetime', 'eliswidget_enrolment');
       return [$visible, $hidden];
    }

    /**
     * Converts an array of requested filter data into an SQL WHERE clause.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters = array()) {
        if (!empty($this->programid)) {
            $filters[] = ['sql' => '(element.courseid = ? AND (usrtrk.id IS NULL OR trkass.classid = element.id))', 'params' => [$this->courseid]];
        } else {
            $filters[] = ['sql' => 'element.courseid = ?', 'params' => [$this->courseid]];
        }
        return parent::get_filter_sql($filters);
    }

    /**
     * Set the ID of the course we're getting classes for.
     *
     * @param int $courseid The ID of the course we're getting classes for.
     */
    public function set_courseid($courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Set the ID of the program we're getting classes for.
     *
     * @param int $programid The ID of the course we're getting classes for.
     */
    public function set_programid($programid) {
        $this->programid = $programid;
    }

    /**
     * Get a list of desired table joins to be used in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array with members: First item is an array of JOIN sql fragments, second is an array of parameters used by
     *               the JOIN sql fragments.
     */
    protected function get_join_sql(array $filters = array()) {
        require_once(\elispm::lib('data/usertrack.class.php'));
        list($sql, $params) = parent::get_join_sql($filters);

        // Custom field joins.
        $enabledcfields = array_intersect_key($this->customfields, $this->availablefilters);
        $ctxlevel = \local_eliscore\context\helper::get_level_from_name('class');
        // Get current user id.
        $euserid = \user::get_current_userid();

        $newsql = $this->get_custom_field_joins($ctxlevel, $enabledcfields);
        $newparams = [];

        // Get optional program for track class filtering
        if (!empty($this->programid)) {
            $newparams = [$euserid, $this->programid];
            $newsql[] =  'LEFT JOIN ({'.\track::TABLE.'} trk
                                     JOIN {'.\usertrack::TABLE.'} usrtrk ON trk.id = usrtrk.trackid
                                          AND usrtrk.userid = ?
                                     JOIN {'.\trackassignment::TABLE.'} trkass ON trk.id = trkass.trackid) ON trk.curid = ?
                                    AND trkass.courseid = element.courseid';
        }

        // Enrolment and waitlist information.
        $newsql[] = 'LEFT JOIN {local_elisprogram_cls_enrol} enrol ON enrol.classid = element.id AND enrol.userid = ?';
        $newsql[] = 'LEFT JOIN {local_elisprogram_waitlist} waitlist ON waitlist.classid = element.id AND waitlist.userid = ?';
        $newparams = array_merge($newparams, [$euserid, $euserid]);

        return [array_merge($sql, $newsql), array_merge($params, $newparams)];
    }

    /**
     * Get an ORDER BY sql fragment to be used in the get_searcH_results method.
     *
     * @return string An ORDER BY sql fragment, if desired.
     */
    protected function get_sort_sql() {
        return 'ORDER BY element.idnumber ASC';
    }

    /**
     * Get an array of fields to select in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of fields to select.
     */
    protected function get_select_fields(array $filters = array()) {
        $selectfields = parent::get_select_fields($filters);
        $selectfields[] = 'element.maxstudents AS maxstudents';
       /*
        $selectfields[] = '(SELECT COUNT("id")
                              FROM {local_elisprogram_cls_enrol} enrol2
                             WHERE enrol2.classid = element.id) AS totalstudents';
       */
        return $selectfields;
    }

    /**
     * Get courses assigned to a program.
     *
     * @param int $programid The ID of the program to get courses for.
     * @return \moodle_recordset A recordset of course information.
     */
    public function get_search_results(array $filters = array(), $page = 1) {
        global $CFG, $DB;
        require_once(\elispm::lib('data/classmoodlecourse.class.php'));
        require_once(\elispm::lib('data/student.class.php'));
        require_once(\elispm::lib('data/waitlist.class.php'));
        list($pageresults, $totalresultsamt) = parent::get_search_results($filters, $page);

        if ($totalresultsamt <= 0) {
            return [$pageresults, $totalresultsamt];
        }

        // Note: get_search results returns a recordset, so we also array-ify pageresults - recordsets are one-time-use.
        $timenow = time();
        $enrolallowed = true;
        if (($classids = $DB->get_records($this->maintable, array('courseid' => $this->courseid), '', 'id'))) {
            $userfilter = new \field_filter('userid', \user::get_current_userid());
            $classfilter =  new \in_list_filter('classid', array_keys($classids));
            $enrolallowed = !\student::exists(array($userfilter, $classfilter)) && !\waitlist::exists(array($userfilter, $classfilter));
        }
        // Assemble class ids.
        $classids = [];
        $pageresultsar = [];
        $dateformat = get_string('strftimedate');
        foreach ($pageresults as $id => $result) {
            $classids[] = $result->id;
            $pageresultsar[$id] = $result;
            $pageresultsar[$id]->instructors = [];
            if (!isset($pageresultsar[$id]->meta)) {
                $pageresultsar[$id]->meta = new \stdClass;
            }
            $pageresultsar[$id]->meta->enrolallowed = $enrolallowed;
            $pageresultsar[$id]->meta->limit = $result->maxstudents;
            $classfilter = new \field_filter('classid', $result->id);
            $pageresultsar[$id]->meta->total = \student::count($classfilter);
            $pageresultsar[$id]->meta->waiting = \waitlist::count($classfilter);
            $pageresultsar[$id]->meta->waitpos = !empty($result->waitlist_id) ? $DB->count_records_select(\waitlist::TABLE, 'classid = ? AND id <= ?',
                    array($result->id, $result->waitlist_id)) : false;
            if (isset($pageresultsar[$id]->element_startdate)) {
                if ($pageresultsar[$id]->element_startdate > 0) {
                    $pageresultsar[$id]->element_startdate = userdate($pageresultsar[$id]->element_startdate, $dateformat);
                } else {
                    $pageresultsar[$id]->element_startdate = get_string('notavailable');
                }
            }
            if (isset($pageresultsar[$id]->element_enddate)) {
                if ($pageresultsar[$id]->element_enddate > 0) {
                    if ($pageresultsar[$id]->element_enddate < $timenow) {
                        $pageresultsar[$id]->meta->enrolallowed = false;
                    }
                    $pageresultsar[$id]->element_enddate = userdate($pageresultsar[$id]->element_enddate, $dateformat);
                } else {
                    $pageresultsar[$id]->element_enddate = get_string('notavailable');
                }
            }
            if (($mdlcourse = moodle_get_course($result->id)) && ($mdlcrsrec = $DB->get_record('course', array('id' => $mdlcourse)))) {
                $pageresultsar[$id]->element_idnumber .= ' - '.\html_writer::tag('a', get_string('moodlecourse_enrolwidget', 'local_elisprogram', $mdlcrsrec),
                        array('href' => $CFG->wwwroot.'/course/view.php?id='.$mdlcourse));
            }
            if (isset($pageresultsar[$id]->enrol_completetime) && !empty($pageresultsar[$id]->enrol_completetime)) {
                $pageresultsar[$id]->enrol_completetime = userdate($pageresultsar[$id]->enrol_completetime, $dateformat);
            } else {
                $pageresultsar[$id]->enrol_completetime = get_string('notavailable');
            }
        }
        unset($pageresults);

        // Get instructor information for each class.
        list($clsidinsql, $clsidinparams) = $DB->get_in_or_equal($classids);
        $sql = 'SELECT ins.classid,
                       ins.userid,
                       usr.email,
                       usr.firstname,
                       usr.lastname
                  FROM {local_elisprogram_cls_nstrct} ins
                  JOIN {local_elisprogram_usr} usr ON ins.userid = usr.id
                 WHERE ins.classid '.$clsidinsql;
        $instructors = $DB->get_recordset_sql($sql, $clsidinparams);
        $instructorsbyclass = [];
        foreach ($instructors as $i => $instructor) {
            if (isset($pageresultsar[$instructor->classid])) {
                $pageresultsar[$instructor->classid]->instructors[] = $instructor;
            }
        }

        // Return the results. Note: array_values is used to convert $pageresultsar into a numeric array, which jsonencode will
        // encode to a js array. An associative array will be converted into a js object which will cause problems on the front
        // end.
        return [array_values($pageresultsar), $totalresultsamt];
    }
}
