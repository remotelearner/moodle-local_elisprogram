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
 * @copyright  (C) 2014 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_trackenrol;

/**
 * A widget allowing students to enrol in classes.
 */
class widget extends \local_elisprogram\lib\widgetbase {

    /**
     * Get HTML to display the widget.
     *
     * @param bool $fullscreen Whether the widget is being displayed full-screen or not.
     * @return string The HTML to display the widget.
     */
    public function get_html($fullscreen = false) {
        global $CFG;
        $uniqid = uniqid();

        $html = \html_writer::start_tag('div', ['id' => $uniqid]);

        $html .= \html_writer::tag('div', \html_writer::tag('div', '', array('id' => 'childrenlist', 'class' => 'childrenlist', 'style' => 'display:inline;')),
                array('id' => 'track', 'class' => 'track', 'data-id' => 'none'));

        $enrolallowed = get_config('enrol_elis', 'enrol_from_course_catalog');
        $enrolallowed = (!empty($enrolallowed) && $enrolallowed == '1') ? '1' : '0';
        $unenrolallowed = get_config('enrol_elis', 'unenrol_from_course_catalog');
        $unenrolallowed = (!empty($unenrolallowed) && $unenrolallowed == '1') ? '1' : '0';

        $initopts = [
            'endpoint' => $CFG->wwwroot.'/local/elisprogram/widgets/trackenrol/ajax.php',
            'enrolallowed' => $enrolallowed,
            'unenrolallowed' => $unenrolallowed,
            'lang' => [
                'status_available' => get_string('status_available', 'eliswidget_trackenrol'),
                'status_notenroled' => get_string('status_notenroled', 'eliswidget_trackenrol'),
                'status_enroled' => get_string('status_enroled', 'eliswidget_trackenrol'),
                'status_unavailable' => get_string('status_unavailable', 'eliswidget_trackenrol'),
                'max' => get_string('max', 'eliswidget_trackenrol'),
                'more' => get_string('more', 'eliswidget_trackenrol'),
                'of' => get_string('of', 'eliswidget_trackenrol'),
                'less' => get_string('less', 'eliswidget_trackenrol'),
                'data_status' => get_string('data_status', 'eliswidget_trackenrol'),
                'action_unenrol' => get_string('action_unenrol', 'eliswidget_trackenrol'),
                'action_enrol' => get_string('action_enrol', 'eliswidget_trackenrol'),
                'working' => get_string('working', 'eliswidget_trackenrol'),
                'nonefound' => get_string('nonefound', 'eliswidget_trackenrol'),
                'generatortitle' => get_string('generatortitle', 'eliswidget_trackenrol'),
                'cancel' => get_string('cancel'),
                'enddate' => get_string('enddate', 'eliswidget_trackenrol'),
                'enrol_confirm_enrol' => get_string('enrol_confirm_enrol', 'eliswidget_trackenrol'),
                'enrol_confirm_title' => get_string('enrol_confirm_title', 'eliswidget_trackenrol'),
                'enrol_confirm_unenrol' => get_string('enrol_confirm_unenrol', 'eliswidget_trackenrol'),
                'idnumber' => get_string('idnumber', 'eliswidget_trackenrol'),
                'startdate' => get_string('startdate', 'eliswidget_trackenrol'),
                'enrolled' => get_string('enrolled', 'eliswidget_trackenrol'),
                'waiting' => get_string('waiting', 'eliswidget_trackenrol'),
                'yes' => get_string('yes'),
                'program' => get_string('track_program', 'eliswidget_trackenrol'),
                'tracks' => get_string('tracks', 'eliswidget_trackenrol'),
            ],
        ];
        $initjs = "\n(function($) {"."\n";
        $initjs .= "$(function() { ";
        $initjs .= "$('#".$uniqid."').parents('.eliswidget_trackenrol').eliswidget_trackenrol(".json_encode($initopts)."); ";
        $initjs .= "});\n";
        $initjs .= "\n".'})(jQuery); jQuery.noConflict()';
        $html .= \html_writer::tag('script', $initjs);

        $html .= \html_writer::end_tag('div');
        return $html;
    }

    /**
     * Get an array of CSS files that are needed by the widget.
     *
     * @param bool $fullscreen Whether the widget is being displayed full-screen or not.
     * @return array Array of URLs or \moodle_url objects to require for the widget.
     */
    public function get_css_dependencies($fullscreen = false) {
        return [
                new \moodle_url('/local/elisprogram/widgets/enrolment/css/widget.css'),
                new \moodle_url('/local/elisprogram/widgets/trackenrol/css/widget.css')
        ];
    }

    /**
     * Get an array of js files that are needed by the widget.
     *
     * @param bool $fullscreen Whether the widget is being displayed full-screen or not.
     * @return array Array of URLs or \moodle_url objects to require for the widget.
     */
    public function get_js_dependencies($fullscreen = false) {
        return [
                new \moodle_url('/local/elisprogram/lib/deepsight/js/support.js'),
                new \moodle_url('/local/elisprogram/lib/deepsight/js/filters/deepsight_filterbar.js'),
                new \moodle_url('/local/elisprogram/lib/deepsight/js/filters/deepsight_filter_generator.js'),
                new \moodle_url('/local/elisprogram/lib/deepsight/js/filters/deepsight_filter_textsearch.js'),
                new \moodle_url('/local/elisprogram/lib/deepsight/js/filters/deepsight_filter_date.js'),
                new \moodle_url('/local/elisprogram/lib/deepsight/js/filters/deepsight_filter_searchselect.js'),
                new \moodle_url('/local/elisprogram/widgets/trackenrol/js/widget.js'),
                new \moodle_url('/local/elisprogram/lib/deepsight/js/buttons/deepsight_tools_button.js'),
                new \moodle_url('/local/elisprogram/lib/deepsight/js/buttons/deepsight_loadsearch_button.js'),
                new \moodle_url('/local/elisprogram/lib/deepsight/js/buttons/deepsight_savesearch_button.js'),
        ];
    }

    /**
     * Get Moodle userid for widget display.
     * @return int the Moodle user id to display for.
     */
    public static function get_userid() {
        global $USER;
        return $USER->id;
    }

    /**
     * Get a list of capabilities a user must have to be able to add this widget.
     *
     * @return array An array of capabilities the user must have to add this widget.
     */
    public function get_required_capabilities() {
        global $DB;
        $viewcap = get_config('eliswidget_trackenrol', 'viewcap');
        $reqcaps = [];
        if (!empty($viewcap)) {
            foreach (explode(',', $viewcap) as $capid) {
                if (empty($capid)) {
                    return [];
                }
                $capname = $DB->get_field('capabilities', 'name', ['id' => $capid]);
                if (!empty($capname)) {
                    $reqcaps[] = $capname;
                }
            }
        }
        return $reqcaps;
    }

    /**
     * Determine whether a user has the required capabilities to add this widget.
     *
     * @return bool Whether the user has the required capabilities to add this widget.
     */
    public function has_required_capabilities() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');
        require_once(\elispm::lib('data/clusterassignment.class.php'));
        require_once(\elispm::lib('lib.php'));
        $userid = static::get_userid();
        if (!($pmuserid = pm_get_crlmuserid($userid))) {
            return false;
        }
        $viewusersetstree = get_config('eliswidget_trackenrol', 'viewusersetstree');
        if (!empty($viewusersetstree)) {
            // Is user in an allowed Userset?
            $clustertreefilter = \local_elisprogram\admin\setting\usersetselect::init_filter('clustass', 'clusterid');
            if (($filterdata = $clustertreefilter->check_data((object)@unserialize($viewusersetstree)))) {
                $filtersql = $clustertreefilter->get_sql_filter($filterdata);
                if (!empty($filtersql[0])) {
                    $where = ["clustass.userid = {$pmuserid}", '('.$filtersql[0].')'];
                    $sql = 'SELECT clustass.clusterid
                              FROM {local_elisprogram_uset_asign} clustass
                             WHERE '.implode(' AND ', $where);
                    if ($DB->record_exists_sql($sql, $filtersql[1])) {
                        return true;
                    }
                } else {
                    $viewusersetstree = false;
                }
            } else {
                $viewusersetstree = false;
            }
        }
        $requiredcaps = $this->get_required_capabilities();
        if (!empty($requiredcaps)) {
            $viewcontexts = get_config('eliswidget_trackenrol', 'viewcontexts');
            $viewcontexts = explode(',', $viewcontexts);
            if (empty($viewcontexts) || in_array(CONTEXT_SYSTEM, $viewcontexts)) {
                $syscontext = \context_system::instance();
                foreach ($requiredcaps as $requiredcap) {
                    if (has_capability($requiredcap, $syscontext, $userid)) {
                        return true;
                    }
                }
            }
            $contextsql = '';
            $params = [$userid];
            // Any context permitted?
            if (!empty($viewcontexts) && !in_array('', $viewcontexts)) {
                $contextlevels = $DB->get_in_or_equal($viewcontexts);
                if (!empty($contextlevels[0])) {
                    $contextsql = ' AND c.contextlevel '.$contextlevels[0];
                    $params = array_merge($params, $contextlevels[1]);
                }
            }
            $sql = 'SELECT c.id, c.instanceid, c.contextlevel
                      FROM {role_assignments} ra
                      JOIN {context} c ON ra.contextid = c.id
                     WHERE ra.userid = ? '.$contextsql;
            $possiblecontexts = $DB->get_recordset_sql($sql, $params);
            foreach ($possiblecontexts as $c) {
                $ctxclass = \context_helper::get_class_for_level($c->contextlevel);
                $context = $ctxclass::instance($c->instanceid);
                foreach ($requiredcaps as $requiredcap) {
                    if (has_capability($requiredcap, $context, $userid)) {
                        return true;
                    }
                }
            }
            return false;
        }
        if (empty($viewusersetstree) && empty($requiredcaps)) {
            return true;
        } else {
            return false;
        }
    }
}
