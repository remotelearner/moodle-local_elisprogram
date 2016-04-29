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
 * @package    eliswidget_teachingplan
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

namespace eliswidget_teachingplan;

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
        require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');
        require_once(\elispm::lib('data/user.class.php'));
        require_once(\elispm::lib('lib.php'));

        $uniqid = uniqid();

        $html = \html_writer::start_tag('div', ['id' => $uniqid]);

        $euserid = \user::get_current_userid();

        $html .= \html_writer::tag('div', \html_writer::tag('div', '', array('id' => 'childrenlist', 'class' => 'childrenlist', 'style' => 'display:inline;')),
                array('id' => 'course', 'class' => 'teachingplan', 'data-id' => 'none'));

        $initopts = [
            'endpoint' => $CFG->wwwroot.'/local/elisprogram/widgets/teachingplan/ajax.php',
            'lang' => [
                'maxstudents' => get_string('maxstudents', 'eliswidget_teachingplan'),
                'more' => get_string('more', 'eliswidget_teachingplan'),
                'of' => get_string('of', 'eliswidget_teachingplan'),
                'less' => get_string('less', 'eliswidget_teachingplan'),
                'course' => get_string('course', 'eliswidget_teachingplan'),
                'courses' => get_string('courses', 'eliswidget_teachingplan'),
                'classes' => get_string('classes', 'eliswidget_teachingplan'),
                'description' => get_string('data_description', 'eliswidget_teachingplan'),
                'working' => get_string('working', 'eliswidget_teachingplan'),
                'nonefound' => get_string('nonefound', 'eliswidget_teachingplan'),
                'generatortitle' => get_string('generatortitle', 'eliswidget_teachingplan'),
                'cancel' => get_string('cancel'),
                'classtime' => get_string('classtime', 'eliswidget_teachingplan'),
                'completiongrade' => get_string('data_completiongrade', 'eliswidget_teachingplan'),
                'credits' => get_string('data_credits', 'eliswidget_teachingplan'),
                'enddate' => get_string('enddate', 'eliswidget_teachingplan'),
                'class_header' => get_string('class_table_class_header', 'eliswidget_teachingplan'),
                'instructors' => get_string('instructors', 'eliswidget_teachingplan'),
                'moodletime' => get_string('moodletime', 'eliswidget_teachingplan'),
                'name' => get_string('data_name', 'eliswidget_teachingplan'),
                'startdate' => get_string('startdate', 'eliswidget_teachingplan'),
                'enrolled' => get_string('enrolled', 'eliswidget_teachingplan'),
                'waiting' => get_string('waiting', 'eliswidget_teachingplan'),
                'yes' => get_string('yes'),
                'programs' => get_string('data_programs', 'eliswidget_teachingplan'),
                'hide_classes' => get_string('hide_classes', 'eliswidget_teachingplan'),
                'show_classes' => get_string('show_classes', 'eliswidget_teachingplan'),
            ],
        ];
        $initjs = "\n(function($) {"."\n";
        $initjs .= "$(function() { ";
        $initjs .= "$('#".$uniqid."').parents('.eliswidget_teachingplan').eliswidget_teachingplan(".json_encode($initopts)."); ";
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
        $config = get_config('eliswidget_teachingplan');
        if (!empty($config->progressbarenabled)) {
            $html .= '<style>'."\n";
            $html .= '.eliswidget_teachingplan svg.elisprogress rect.colorcode1 { fill: '.$config->progressbarcolor1.'; }'."\n";
            $html .= '.eliswidget_teachingplan svg.elisprogress rect.colorcode2 { fill: '.$config->progressbarcolor2.'; }'."\n";
            $html .= '.eliswidget_teachingplan svg.elisprogress rect.colorcode3 { fill: '.$config->progressbarcolor3.'; }'."\n";
            $html .= '</style>'."\n";
        }
        echo $html;
        return [new \moodle_url('/local/elisprogram/widgets/teachingplan/css/widget.css')];
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
                new \moodle_url('/local/elisprogram/widgets/teachingplan/js/widget.js'),
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
        return ['eliswidget/teachingplan:addwidget'];
    }

    /**
     * Determine whether a user has the required capabilities to add this widget.
     *
     * @return bool Whether the user has the required capabilities to add this widget.
     */
    public function has_required_capabilities() {
        global $DB;
        $userid = static::get_userid();
        $requiredcaps = $this->get_required_capabilities();

        // First check system context.
        $syscontext = \context_system::instance();
        foreach ($requiredcaps as $requiredcap) {
            if (has_capability($requiredcap, $syscontext, $userid)) {
                return true;
            }
        }

        // Check for capabilities on all other non-system contexts.
        $sql = 'SELECT c.id, c.instanceid, c.contextlevel
                  FROM {role_assignments} ra
                  JOIN {context} c ON ra.contextid = c.id
                 WHERE ra.userid = ?';
        $possiblecontexts = $DB->get_recordset_sql($sql, [$userid]);
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
}
