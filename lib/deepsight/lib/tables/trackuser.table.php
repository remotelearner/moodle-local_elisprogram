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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

/**
 * A base datatable object for track - user assignments.
 */
class deepsight_datatable_trackuser_base extends deepsight_datatable_user {
    protected $trackid;

    /**
     * Sets the current track ID
     * @param int $trackid The ID of the track to use.
     */
    public function set_trackid($trackid) {
        $this->trackid = (int)$trackid;
    }

    /**
     * Gets an array of javascript files needed for operation.
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/local/elisprogram/lib/deepsight/js/actions/deepsight_action_confirm.js';
        return $deps;
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object. Enables drag and drop, and multiselect
     * @return array An array of options, ready to be passed to $this->get_init_js()
     */
    public function get_table_js_opts() {
        $opts = parent::get_table_js_opts();
        $opts['dragdrop'] = true;
        $opts['multiselect'] = true;
        return $opts;
    }

    /**
     * Gets filter sql for permissions.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        $elementtype = 'track';
        $elementid = $this->trackid;
        $elementid2clusterscallable = 'clustertrack::get_clusters';
        return $this->get_filter_sql_permissions_elementuser($elementtype, $elementid, $elementid2clusterscallable);
    }
}

/**
 * A datatable object for users assigned to the track.
 */
class deepsight_datatable_trackuser_assigned extends deepsight_datatable_trackuser_base {
    /**
     * Gets an array of javascript files needed for operation.
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/local/elisprogram/lib/deepsight/js/actions/deepsight_action_usertrack.js';
        return $deps;
    }

    /**
     * Gets the unassignment action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $unassignaction = new deepsight_action_trackuser_unassign($this->DB, 'trackuserunassign');
        $unassignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $unassignaction);
        return $actions;
    }

    /**
     * Adds the assignment table for this track.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {'.usertrack::TABLE.'} trkass ON trkass.trackid='.$this->trackid.' AND trkass.userid = element.id';
        return $joinsql;
    }

    /**
     * Removes assigned users, controls display permissions.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Add permissions.
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

    /**
     * Formats the hasgrades params.
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        global $DB;
        $row = parent::results_row_transform($row);
        $hasgrades = false;
        $track = new track($this->trackid);
        foreach ($track->trackassignment as $trackass) {
            if (($stu = student::get_userclass($row['element_id'], $trackass->classid)) && $stu->grade > 0) {
                $hasgrades = true;
                break;
            }
        }
        $row['meta']['hasgrades'] = $hasgrades;
        $row['meta']['inprogram'] = curriculumstudent::exists([new field_filter('curriculumid', $track->curriculum->id),
                new field_filter('userid', $row['element_id'])]);
        $sql = 'SELECT trkass.id
                  FROM {'.trackassignment::TABLE.'} trkass
                  JOIN {'.student::TABLE.'} stu ON stu.classid = trkass.classid
                       AND stu.userid = ?
                 WHERE trkass.trackid = ?';
        $row['meta']['intrackclass'] = $DB->record_exists_sql($sql, [$row['element_id'], $this->trackid]);
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
                                      window.leptrackhasgradeslist.push(rowdata.element_id);
                                  }
                                  if (rowdata.meta.inprogram) {
                                      window.leptrackuserinprogramlist.push(rowdata.element_id);
                                  }
                                  if (rowdata.meta.intrackclass) {
                                      window.leptrackuserinclasslist.push(rowdata.element_id);
                                  }
                                  return row;
                              }';
        return $opts;
    }
}

/**
 * A datatable for users not yet assigned to the track.
 */
class deepsight_datatable_trackuser_available extends deepsight_datatable_trackuser_base {

    /**
     * Gets the track assignment action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $assignaction = new deepsight_action_trackuser_assign($this->DB, 'trackuserassign');
        $assignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $assignaction);
        return $actions;
    }

    /**
     * Adds the assignment table for this track.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'LEFT JOIN {'.usertrack::TABLE.'} trkass ON trkass.trackid='.$this->trackid.' AND trkass.userid = element.id';
        return $joinsql;
    }

    /**
     * Removes assigned users, controls display permissions.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        global $USER;

        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Limit to users not currently assigned.
        $additionalfilters[] = 'trkass.id IS NULL';

        // Add permissions.
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
