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
 * @package    eliswidget_learningplan
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_learningplan;

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
        if (!$init && get_config('eliswidget_learningplan', 'syncusergrades') && isloggedin() && !empty($USER->id) && !isguestuser($USER->id)) {
            $init = true;
            pm_update_user_information($USER->id);
        }

        $uniqid = uniqid();

        $html = \html_writer::start_tag('div', ['id' => $uniqid]);

        $euserid = \user::get_current_userid();

        $html .= \html_writer::tag('div', \html_writer::tag('div', '', array('id' => 'childrenlist', 'class' => 'childrenlist', 'style' => 'display:inline;')),
                array('id' => 'program', 'class' => 'learningplan', 'data-id' => 'none'));

        $initopts = [
            'endpoint' => $CFG->wwwroot.'/local/elisprogram/widgets/learningplan/ajax.php',
            'lang' => [
                'status_enroled' => get_string('status_enroled', 'eliswidget_learningplan'),
                'status_notenroled' => get_string('status_notenroled', 'eliswidget_learningplan'),
                'status_passed' => get_string('status_passed', 'eliswidget_learningplan'),
                'status_failed' => get_string('status_failed', 'eliswidget_learningplan'),
                'status_full' => get_string('status_full', 'eliswidget_learningplan'),
                'status_waitlist' => get_string('status_waitlist', 'eliswidget_learningplan'),
                'status_prereqnotmet' => get_string('status_prereqnotmet', 'eliswidget_learningplan'),
                'max' => get_string('max', 'eliswidget_learningplan'),
                'more' => get_string('more', 'eliswidget_learningplan'),
                'of' => get_string('of', 'eliswidget_learningplan'),
                'less' => get_string('less', 'eliswidget_learningplan'),
                'coursesets' => get_string('coursesets', 'eliswidget_learningplan'),
                'course' => get_string('course', 'eliswidget_learningplan'),
                'courses' => get_string('courses', 'eliswidget_learningplan'),
                'classes' => get_string('classes', 'eliswidget_learningplan'),
                'data_status' => get_string('data_status', 'eliswidget_learningplan'),
                'data_grade' => get_string('data_grade', 'eliswidget_learningplan'),
                'data_completetime' => get_string('data_completetime', 'eliswidget_learningplan'),
                'data_instructors' => get_string('data_instructors', 'eliswidget_learningplan'),
                'working' => get_string('working', 'eliswidget_learningplan'),
                'nonefound' => get_string('nonefound', 'eliswidget_learningplan'),
                'generatortitle' => get_string('generatortitle', 'eliswidget_learningplan'),
                'cancel' => get_string('cancel'),
                'enddate' => get_string('enddate', 'eliswidget_learningplan'),
                'idnumber' => get_string('idnumber', 'eliswidget_learningplan'),
                'startdate' => get_string('startdate', 'eliswidget_learningplan'),
                'enrolled' => get_string('enrolled', 'eliswidget_learningplan'),
                'waiting' => get_string('waiting', 'eliswidget_learningplan'),
                'yes' => get_string('yes'),
                'programs' => get_string('programs', 'eliswidget_learningplan'),
                'hide_classes' => get_string('hide_classes', 'eliswidget_learningplan'),
                'show_classes' => get_string('show_classes', 'eliswidget_learningplan'),
            ],
        ];
        $initjs = "\n(function($) {"."\n";
        $initjs .= "$(function() { ";
        $initjs .= "$('#".$uniqid."').parents('.eliswidget_learningplan').eliswidget_learningplan(".json_encode($initopts)."); ";
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
        $config = get_config('eliswidget_learningplan');
        if (!empty($config->progressbarenabled)) {
            $html .= '<style>'."\n";
            $html .= '.eliswidget_learningplan svg.elisprogress rect.colorcode1 { fill: '.$config->progressbarcolor1.'; }'."\n";
            $html .= '.eliswidget_learningplan svg.elisprogress rect.colorcode2 { fill: '.$config->progressbarcolor2.'; }'."\n";
            $html .= '.eliswidget_learningplan svg.elisprogress rect.colorcode3 { fill: '.$config->progressbarcolor3.'; }'."\n";
            $html .= '</style>'."\n";
        }
        echo $html;
        return [new \moodle_url('/local/elisprogram/widgets/learningplan/css/widget.css')];
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
                new \moodle_url('/local/elisprogram/widgets/learningplan/js/widget.js'),
        ];
    }
}
