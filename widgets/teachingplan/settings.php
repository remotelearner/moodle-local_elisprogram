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
 * @copyright  (C) 2015 Onwards Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

defined('MOODLE_INTERNAL') || die;

global $USER;

if ($ADMIN->fulltree) {
    global $CFG;

    $settings->add(new \admin_setting_heading('classlisting', get_string('class_listing_heading', 'eliswidget_teachingplan'),
            get_string('class_listing_heading_description', 'eliswidget_teachingplan')));

    $settings->add(new \admin_setting_configcheckbox('eliswidget_teachingplan/startdate', get_string('column_startdate', 'eliswidget_teachingplan'),
            get_string('column_startdate_description', 'eliswidget_teachingplan'), 1));

    $settings->add(new \admin_setting_configcheckbox('eliswidget_teachingplan/enddate', get_string('column_enddate', 'eliswidget_teachingplan'),
            get_string('column_enddate_description', 'eliswidget_teachingplan'), 1));

    $settings->add(new \admin_setting_configcheckbox('eliswidget_teachingplan/classtime', get_string('column_classtime', 'eliswidget_teachingplan'),
            get_string('column_classtime_description', 'eliswidget_teachingplan'), 1));

    $settings->add(new \admin_setting_configcheckbox('eliswidget_teachingplan/moodletime', get_string('column_moodletime', 'eliswidget_teachingplan'),
            get_string('column_moodletime_description', 'eliswidget_teachingplan'), 0));

    $settings->add(new \admin_setting_configcheckbox('eliswidget_teachingplan/maxstudents', get_string('column_maxstudents', 'eliswidget_teachingplan'),
            get_string('column_maxstudents_description', 'eliswidget_teachingplan'), 1));

    $settings->add(new \admin_setting_configcheckbox('eliswidget_teachingplan/enrolledstudents', get_string('column_enrolledstudents', 'eliswidget_teachingplan'),
            get_string('column_enrolledstudents_description', 'eliswidget_teachingplan'), 1));

    $settings->add(new \admin_setting_configcheckbox('eliswidget_teachingplan/instructors', get_string('column_instructors', 'eliswidget_teachingplan'),
            get_string('column_instructors_description', 'eliswidget_teachingplan'), 0));

    // Progress bar.
    $progressbarheader = get_string('setting_progressbar_heading', 'eliswidget_teachingplan');
    $progressbarheaderdesc = get_string('setting_progressbar_heading_description', 'eliswidget_teachingplan');
    $settings->add(new \admin_setting_heading('progressbar', $progressbarheader, $progressbarheaderdesc));

    $progressbarenabled = [
        'name' => 'eliswidget_teachingplan/progressbarenabled',
        'visiblename' => get_string('setting_progressbar_enabled', 'eliswidget_teachingplan'),
        'description' => get_string('setting_progressbar_enableddescription', 'eliswidget_teachingplan'),
        'defaultsetting' => 1
    ];
    $settings->add(new \admin_setting_configcheckbox($progressbarenabled['name'], $progressbarenabled['visiblename'],
            $progressbarenabled['description'], $progressbarenabled['defaultsetting']));

    $defaultcolors = ['#A70505', '#D5D100', '#009029'];
    for ($i = 1; $i <= 3; $i++) {
        $progressbarcolor = [
            'name' => 'eliswidget_teachingplan/progressbarcolor'.$i,
            'visiblename' => get_string('setting_progressbar_color'.$i, 'eliswidget_teachingplan'),
            'description' => get_string('setting_progressbar_color'.$i.'description', 'eliswidget_teachingplan'),
            'defaultsetting' => $defaultcolors[$i - 1]
        ];
        $settings->add(new \admin_setting_configcolourpicker($progressbarcolor['name'], $progressbarcolor['visiblename'],
                $progressbarcolor['description'], $progressbarcolor['defaultsetting']));
    }

}
