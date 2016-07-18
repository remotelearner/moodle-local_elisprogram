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
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

require_once(elispm::lib('data/clustertrack.class.php'));
require_once(elispm::file('plugins/usetclassify/usersetclassification.class.php'));

/**
 * A base class for managing userset - track associations. (one userset, multiple tracks)
 */
class deepsight_datatable_usersettrack_base extends deepsight_datatable_track {
    protected $usersetid;

    /**
     * Sets the current userset ID
     * @param int $usersetid The ID of the userset to use.
     */
    public function set_usersetid($usersetid) {
        $this->usersetid = (int)$usersetid;
    }

    /**
     * Gets an array of javascript files needed for operation.
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/local/elisprogram/lib/deepsight/js/actions/deepsight_action_confirm.js';
        $deps[] = '/local/elisprogram/lib/deepsight/js/actions/deepsight_action_usersettrack.js';
        $deps[] = '/local/elisprogram/lib/deepsight/js/actions/deepsight_action_trackuserset_unassign.js';
        return $deps;
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object. Enables drag and drop, and multiselect.
     * @return array An array of options, ready to be passed to $this->get_init_js()
     */
    public function get_table_js_opts() {
        $opts = parent::get_table_js_opts();
        $opts['dragdrop'] = true;
        $opts['multiselect'] = true;
        return $opts;
    }
}

/**
 * A datatable for listing currently assigned tracks.
 */
class deepsight_datatable_usersettrack_assigned extends deepsight_datatable_usersettrack_base {

    /**
     * Gets an array of available filters.
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $langautoenrol = get_string('usersettrack_autoenrol', 'local_elisprogram');
        $filters = parent::get_filters();
        $fielddata = array('clsttrk.autoenrol' => $langautoenrol);
        $autoenrol = new deepsight_filter_menuofchoices($this->DB, 'autoenrol', $langautoenrol, $fielddata, $this->endpoint);
        $autoenrol->set_choices(array(
            0 => get_string('no', 'moodle'),
            1 => get_string('yes', 'moodle'),
        ));
        $filters[] = $autoenrol;
        return $filters;
    }

    /**
     * Gets an array of initial filters.
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        $initialfilters = parent::get_initial_filters();
        $initialfilters['autoenrol'] = [];
        return $initialfilters;
    }

    /**
     * Formats the autoenrol parameter.
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        global $DB;
        $row = parent::results_row_transform($row);
        if (isset($row['clsttrk_autoenrol'])) {
            // Save original autoenrol value for use by javascript, then convert value to language string.
            $row['autoenrol'] = $row['clsttrk_autoenrol'];
            $row['clsttrk_autoenrol'] = ($row['clsttrk_autoenrol'] == 1) ? get_string('yes', 'moodle') : get_string('no', 'moodle');
        }
        $sql = 'SELECT usass.id
                  FROM {'.clusterassignment::TABLE.'} usass
                  JOIN {'.usertrack::TABLE.'} ut ON ut.userid = usass.userid
                       AND ut.trackid = ?
                  JOIN {'.trackassignment::TABLE.'} trkass ON trkass.trackid = ut.trackid
                  JOIN {'.student::TABLE.'} stu ON stu.userid = ut.userid
                       AND stu.classid = trkass.classid
                 WHERE usass.clusterid = ?
                       AND stu.grade > 0';
        $row['meta']['hasgrades'] = $DB->record_exists_sql($sql, [$row['element_id'], $this->usersetid]);
        $sql = 'SELECT us.id
                  FROM {'.userset::TABLE.'} us
                  JOIN {'.clusterassignment::TABLE.'} usass ON usass.clusterid = us.id
                  JOIN {'.usertrack::TABLE.'} ut ON ut.userid = usass.userid
                       AND ut.trackid = ?
                  JOIN {'.trackassignment::TABLE.'} trkass ON trkass.trackid = ut.trackid
                  JOIN {'.clustertrack::TABLE.'} ustrk ON ustrk.trackid = ut.trackid
                       AND ustrk.clusterid = us.id
                  JOIN {'.student::TABLE.'} stu ON stu.userid = ut.userid
                       AND stu.classid = trkass.classid
                 WHERE us.parent = ?
                       AND stu.grade > 0';
        $row['meta']['subsethasgrades'] = $DB->record_exists_sql($sql, [$row['element_id'], $this->usersetid]);
        // Provide count of users in parent userset and all children.
        $sql = 'SELECT COUNT(usass.id)
                  FROM {'.userset::TABLE.'} us
                  JOIN {'.clusterassignment::TABLE.'} usass ON usass.clusterid = us.id
                  JOIN {'.clustertrack::TABLE.'} ustrk ON ustrk.trackid = ?
                       AND ustrk.clusterid = us.id
                 WHERE us.id = ? OR us.parent = ?';
        $row['meta']['usercount'] = $DB->count_records_sql($sql, [$row['element_id'], $this->usersetid, $this->usersetid]);
        $sql = 'SELECT us.id
                  FROM {'.userset::TABLE.'} us
                  JOIN {'.clustertrack::TABLE.'} ustrk ON ustrk.trackid = ?
                       AND ustrk.clusterid = us.id
                 WHERE us.parent = ?';
        $row['meta']['subsets'] = $DB->record_exists_sql($sql, [$row['element_id'], $this->usersetid]);
        return $row;
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object. Enables drag and drop, and multiselect.
     * @return array An array of options, ready to be passed to $this->get_init_js()
     */
    public function get_table_js_opts() {
        $opts = parent::get_table_js_opts();
        $opts['rowfilter'] = 'function(row, rowdata) {
                                  if (rowdata.meta.hasgrades) {
                                      window.leptrackushasgradeslist.push(rowdata.element_id);
                                  }
                                  if (rowdata.meta.subsethasgrades) {
                                      window.leptracksubsethasgradeslist.push(rowdata.element_id);
                                  }
                                  if (rowdata.meta.subsets) {
                                      window.leptrackussubsetslist.push(rowdata.element_id);
                                  }
                                  window.leptrackusercountlist[rowdata.element_id] = rowdata.meta.usercount;
                                  return row;
                              }';
        return $opts;
    }

    /**
     * Gets the edit and unassignment actions.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $editaction = new deepsight_action_usersettrack_edit($this->DB, 'usersettrackedit');
        $editaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $unassignaction = new deepsight_action_usersettrack_unassign($this->DB, 'usersettrackunassign');
        $unassignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $editaction, $unassignaction);
        return $actions;
    }

    /**
     * Adds the assignment table.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {'.clustertrack::TABLE.'} clsttrk
                           ON clsttrk.clusterid='.$this->usersetid.' AND clsttrk.trackid = element.id';
        return $joinsql;
    }

    /**
     * Gets an array of fields to include in the search SQL's SELECT clause. Adds in autoenrol to ensure auto enrol
     * data is passed to deepsight.
     *
     * Pulls information from $this->fixed_columns, and each filter's get_select_fields() function.
     *
     * @uses deepsight_filter::get_select_fields();
     * @uses deepsight_datatable_standard::$fixed_columns
     * @uses deepsight_datatable_standard::$available_filters
     * @param array $filters An Array of active filters to use to determine the needed select fields.
     * @return array An array of fields for the SELECT clause.
     */
    protected function get_select_fields(array $filters) {
        $selectfields = parent::get_select_fields($filters);
        $selectfields[] = 'autoenrol AS autoenrol';
        return  $selectfields;
    }
}

/**
 * A datatable listing tracks that are available to assign to the userset, and are not currently assigned.
 */
class deepsight_datatable_usersettrack_available extends deepsight_datatable_usersettrack_base {

    /**
     * Gets the assign action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $assignaction = new deepsight_action_usersettrack_assign($this->DB, 'usersettrackassign');
        $assignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $assignaction);
        return $actions;
    }

    /**
     * Adds the assignment table.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'LEFT JOIN {'.clustertrack::TABLE.'} clsttrk
                                ON clsttrk.clusterid = '.$this->usersetid.' AND clsttrk.trackid = element.id';
        return $joinsql;
    }

    /**
     * Gets filter sql for permissions.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        global $USER;
        $additionalfilters = array();
        $additionalparams = array();
        // ELIS-9057.
        $perm = 'local/elisprogram:userset_associatetrack';
        $usassoctrkctxs = pm_context_set::for_user_with_capability('cluster', $perm, $USER->id);
        if ($usassoctrkctxs->context_allowed($this->usersetid, 'cluster')) {
            return array($additionalfilters, $additionalparams);
        }
        $ctxlevel = 'track';
        $perm = 'local/elisprogram:associate';
        $associatectxs = pm_context_set::for_user_with_capability($ctxlevel, $perm, $USER->id);
        $associatectxsfilerobject = $associatectxs->get_filter('id', $ctxlevel);
        $associatefilter = $associatectxsfilerobject->get_sql(false, 'element', SQL_PARAMS_QM);
        if (isset($associatefilter['where'])) {
            $additionalfilters[] = $associatefilter['where'];
            $additionalparams = array_merge($additionalparams, $associatefilter['where_parameters']);
        }
        return array($additionalfilters, $additionalparams);
    }

    /**
     * Removes assigned tracks, and limits results according to permissions.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        global $USER;

        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Remove assigned users.
        $additionalfilters[] = 'clsttrk.id IS NULL';

        // Permissions.
        list($permadditionalfilters, $permadditionalparams) = $this->get_filter_sql_permissions();
        $additionalfilters = array_merge($additionalfilters, $permadditionalfilters);
        $filterparams = array_merge($filterparams, $permadditionalparams);

        // Add our additional filters.
        if (!empty($additionalfilters)) {
            $filtersql = (!empty($filtersql))
                    ? $filtersql.' AND '.implode(' AND ', $additionalfilters) : 'WHERE '.implode(' AND ', $additionalfilters);
        }

        return array($filtersql, $filterparams);
    }
}
