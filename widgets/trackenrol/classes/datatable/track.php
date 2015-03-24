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
 * @package    eliswidget_trackenrol
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_trackenrol\datatable;

/**
 * A datatable implementation for lists of tracks - top-level.
 */
class track extends \eliswidget_enrolment\datatable\base {
    /** @var string The main table results are pulled from. This forms that FROM clause. */
    protected $maintable = 'local_elisprogram_trk';

    /**
     * The number of results displayed per page of the table.
     */
    const RESULTSPERPAGE = 5;

    /** @var int The ID of the Track. */
    protected $trackid = null;

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

        $langidnumber = get_string('track_idnumber', 'eliswidget_trackenrol');
        $langname = get_string('track_name', 'eliswidget_trackenrol');
        $langdescription = get_string('track_description', 'eliswidget_trackenrol');
        $langprogram = get_string('track_program', 'eliswidget_trackenrol');
        $langstartdate = get_string('startdate', 'eliswidget_trackenrol');
        $langenddate = get_string('enddate', 'eliswidget_trackenrol');

        $filters = [
                new \deepsight_filter_textsearch($DB, 'idnumber', $langidnumber, ['element.idnumber' => $langidnumber]),
                new \deepsight_filter_textsearch($DB, 'name', $langname, ['element.name' => $langname]),
                new \deepsight_filter_textsearch($DB, 'program', $langprogram, ['cur.name' => $langprogram]),
                new \deepsight_filter_date($DB, 'startdate', $langstartdate, ['element.startdate' => $langstartdate]),
                new \deepsight_filter_date($DB, 'enddate', $langenddate, ['element.enddate' => $langenddate]),
                new \deepsight_filter_textsearch($DB, 'description', $langdescription, ['element.description' => $langdescription])
        ];

        // Add custom fields.
        $trackctxlevel = \local_eliscore\context\helper::get_level_from_name('track');
        $customfieldfilters = $this->get_custom_field_info($trackctxlevel, ['table' => get_called_class()]);
        $filters = array_merge($filters, $customfieldfilters);

        // Restrict to configured enabled fields.
        $enabledfields = get_config('eliswidget_trackenrol', 'trackenabledfields');
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
     * Get an array of fields to select in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array of fields to select.
     */
    protected function get_select_fields(array $filters = array()) {
        $selectfields = parent::get_select_fields($filters);
        $selectfields[] = 'cur.id AS curid';
        return $selectfields;
    }

    /**
     * Gets an array of fields that will always be selected, regardless of what has been enabled.
     *
     * @return array An array of fields that will always be selected.
     */
    public function get_fixed_select_fields() {
        return [
            'element.idnumber' => '',
            'element.name' => '',
            'element.curid' => '',
            'element.description' => '',
            'element.startdate' => '',
            'element.enddate' => '',
            'cur.name' => '',
            'usertrack.id' => '',
        ];
    }

    /**
     * Get an array of datafields that will always be visible.
     *
     * @return array Array of filter aliases for fields that will always be visible.
     */
    public function get_fixed_visible_datafields() {
        return ['program'];
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
        $hidden['element_idnumber'] = get_string('track_idnumber', 'eliswidget_trackenrol');
        $hidden['element_name'] = get_string('track_name', 'eliswidget_trackenrol');
        $hidden['element_description'] = get_string('track_description', 'eliswidget_trackenrol');
        $hidden['element_startdate'] = get_string('startdate', 'eliswidget_trackenrol');
        $hidden['element_enddate'] = get_string('enddate', 'eliswidget_trackenrol');
        return [$visible, $hidden];
    }

    /**
     * Converts an array of requested filter data into an SQL WHERE clause.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters = array()) {
        global $DB, $USER;
        $trackviewcapid = get_config('eliswidget_trackenrol', 'trackviewcap');
        $trackviewcap = !empty($trackviewcapid) ? $DB->get_field('capabilities', 'name', ['id' => $trackviewcapid]) : null;
        if (!empty($trackviewcap) && !has_capability($trackviewcap, \context_system::instance())) {
            $trkctxs = \pm_context_set::for_user_with_capability('track', $trackviewcap, $USER->id);
            $filterobj = $trkctxs->get_filter('element.id', 'track');
            $trksql = $filterobj->get_sql();
            if (!empty($trksql)) {
                $filters[] = ['sql' => $trksql['where'], 'params' => $trksql['where_parameters']];
            }
        }
        return parent::get_filter_sql($filters);
    }

    /**
     * Get a list of desired table joins to be used in the get_search_results method.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array Array with members: First item is an array of JOIN sql fragments, second is an array of parameters used by
     *               the JOIN sql fragments.
     */
    protected function get_join_sql(array $filters = array()) {
        global $CFG;
        require_once(\elispm::lib('data/usertrack.class.php'));
        list($sql, $params) = parent::get_join_sql($filters);

        // Custom field joins.
        $enabledcfields = array_intersect_key($this->customfields, $this->availablefilters);
        $ctxlevel = \local_eliscore\context\helper::get_level_from_name('track');
        // Get current user id.
        $euserid = \user::get_current_userid();

        $newsql = $this->get_custom_field_joins($ctxlevel, $enabledcfields);
        $newsql[] = 'JOIN {'.\curriculum::TABLE.'} cur ON cur.id = element.curid';
        $newsql[] = 'LEFT JOIN {'.\usertrack::TABLE.'} usertrack ON usertrack.trackid = element.id AND usertrack.userid = ?';
        $newparams = [$euserid];
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
     * Get search results/
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @param int $page The page being displayed.
     * @return \moodle_recordset A recordset of program information.
     */
    public function get_search_results(array $filters = array(), $page = 1) {
        global $CFG;
        require_once(\elispm::lib('data/usertrack.class.php'));
        list($pageresults, $totalresultsamt) = parent::get_search_results($filters, $page);
        $dateformat = get_string('strftimedate');
        $pageresultsar = [];
        foreach ($pageresults as $id => $result) {
            $result->header = get_string('track_header', 'eliswidget_trackenrol', $result);
            $result->program = $result->cur_name; // TBD.
            $pageresultsar[$id] = $result;
            if ($result->element_startdate > 0) {
                $pageresultsar[$id]->element_startdate = userdate($result->element_startdate, $dateformat);
            } else {
                $pageresultsar[$id]->element_startdate = get_string('notavailable');
            }
            if ($result->element_enddate > 0) {
                $pageresultsar[$id]->element_enddate = userdate($result->element_enddate, $dateformat);
            } else {
                $pageresultsar[$id]->element_enddate = get_string('notavailable');
            }
        }

        return [array_values($pageresultsar), count($pageresultsar)];
    }
}
