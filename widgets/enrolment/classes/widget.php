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
 * @package    eliswidget_enrolment
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

namespace eliswidget_enrolment;

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
        global $CFG, $USER;
        static $init = false;
        require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');
        require_once(\elispm::lib('data/user.class.php'));
        require_once(\elispm::lib('lib.php'));
        if (!$init && isloggedin() && !empty($USER->id) && !isguestuser($USER->id)) {
            $init = true;
            pm_update_user_information($USER->id);
        }

        $uniqid = uniqid();

        $html = \html_writer::start_tag('div', ['id' => $uniqid]);

        $euserid = \user::get_current_userid();

        $html .= \html_writer::tag('div', \html_writer::tag('div', '', array('id' => 'childrenlist', 'class' => 'childrenlist', 'style' => 'display:inline;')),
                array('id' => 'program', 'class' => 'program', 'data-id' => 'none'));

        $enrolallowed = get_config('enrol_elis', 'enrol_from_course_catalog');
        $enrolallowed = (!empty($enrolallowed) && $enrolallowed == '1') ? '1' : '0';
        $unenrolallowed = get_config('enrol_elis', 'unenrol_from_course_catalog');
        $unenrolallowed = (!empty($unenrolallowed) && $unenrolallowed == '1') ? '1' : '0';

        $initopts = [
            'endpoint' => $CFG->wwwroot.'/local/elisprogram/widgets/enrolment/ajax.php',
            'enrolallowed' => $enrolallowed,
            'unenrolallowed' => $unenrolallowed,
            'lang' => [
                'status_available' => get_string('status_available', 'eliswidget_enrolment'),
                'status_notenroled' => get_string('status_notenroled', 'eliswidget_enrolment'),
                'status_enroled' => get_string('status_enroled', 'eliswidget_enrolment'),
                'status_passed' => get_string('status_passed', 'eliswidget_enrolment'),
                'status_failed' => get_string('status_failed', 'eliswidget_enrolment'),
                'status_full' => get_string('status_full', 'eliswidget_enrolment'),
                'status_unavailable' => get_string('status_unavailable', 'eliswidget_enrolment'),
                'status_waitlist' => get_string('status_waitlist', 'eliswidget_enrolment'),
                'status_prereqnotmet' => get_string('status_prereqnotmet', 'eliswidget_enrolment'),
                'max' => get_string('max', 'eliswidget_enrolment'),
                'more' => get_string('more', 'eliswidget_enrolment'),
                'of' => get_string('of', 'eliswidget_enrolment'),
                'less' => get_string('less', 'eliswidget_enrolment'),
                'coursesets' => get_string('coursesets', 'eliswidget_enrolment'),
                'courses' => get_string('courses', 'eliswidget_enrolment'),
                'classes' => get_string('classes', 'eliswidget_enrolment'),
                'data_status' => get_string('data_status', 'eliswidget_enrolment'),
                'data_grade' => get_string('data_grade', 'eliswidget_enrolment'),
                'data_instructors' => get_string('data_instructors', 'eliswidget_enrolment'),
                'action_unenrol' => get_string('action_unenrol', 'eliswidget_enrolment'),
                'action_enterwaitlist' => get_string('action_enterwaitlist', 'eliswidget_enrolment'),
                'action_leavewaitlist' => get_string('action_leavewaitlist', 'eliswidget_enrolment'),
                'action_enrol' => get_string('action_enrol', 'eliswidget_enrolment'),
                'working' => get_string('working', 'eliswidget_enrolment'),
                'nonefound' => get_string('nonefound', 'eliswidget_enrolment'),
                'generatortitle' => get_string('generatortitle', 'eliswidget_enrolment'),
                'cancel' => get_string('cancel'),
                'enddate' => get_string('enddate', 'eliswidget_enrolment'),
                'enrol_confirm_enrol' => get_string('enrol_confirm_enrol', 'eliswidget_enrolment'),
                'enrol_confirm_enterwaitlist' => get_string('enrol_confirm_enterwaitlist', 'eliswidget_enrolment'),
                'enrol_confirm_leavewaitlist' => get_string('enrol_confirm_leavewaitlist', 'eliswidget_enrolment'),
                'enrol_confirm_title' => get_string('enrol_confirm_title', 'eliswidget_enrolment'),
                'enrol_confirm_unenrol' => get_string('enrol_confirm_unenrol', 'eliswidget_enrolment'),
                'idnumber' => get_string('idnumber', 'eliswidget_enrolment'),
                'startdate' => get_string('startdate', 'eliswidget_enrolment'),
                'enrolled' => get_string('enrolled', 'eliswidget_enrolment'),
                'waiting' => get_string('waiting', 'eliswidget_enrolment'),
                'yes' => get_string('yes'),
                'programs' => get_string('programs', 'eliswidget_enrolment'),
            ],
        ];
        $initjs = "\n(function($) {"."\n";
        $initjs .= "$(function() { ";
        $initjs .= "$('#".$uniqid."').parents('.eliswidget_enrolment').eliswidget_enrolment(".json_encode($initopts)."); ";
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
        $html = '';
        $config = get_config('eliswidget_enrolment');
        if (!empty($config->progressbarenabled)) {
            $html .= '<style>'."\n";
            $html .= 'svg.elisprogress rect.colorcode1 { fill: '.$config->progressbarcolor1.'; }'."\n";
            $html .= 'svg.elisprogress rect.colorcode2 { fill: '.$config->progressbarcolor2.'; }'."\n";
            $html .= 'svg.elisprogress rect.colorcode3 { fill: '.$config->progressbarcolor3.'; }'."\n";
            $html .= '</style>'."\n";
        }
        echo $html;
        return [new \moodle_url('/local/elisprogram/widgets/enrolment/css/widget.css')];
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
                new \moodle_url('/local/elisprogram/widgets/enrolment/js/widget.js'),
        ];
    }
}
