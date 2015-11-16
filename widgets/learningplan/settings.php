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
 * @copyright  (C) 2015 Onwards Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

defined('MOODLE_INTERNAL') || die;

global $USER;

if ($ADMIN->fulltree) {
    global $CFG;

    $settings->add(new \admin_setting_configcheckbox('eliswidget_learningplan/syncusergrades', get_string('setting_syncusergrades', 'eliswidget_learningplan'),
            get_string('setting_syncusergrades_description', 'eliswidget_learningplan'), 1));

    // Progress bar.
    $progressbarheader = get_string('setting_progressbar_heading', 'eliswidget_learningplan');
    $progressbarheaderdesc = get_string('setting_progressbar_heading_description', 'eliswidget_learningplan');
    $settings->add(new \admin_setting_heading('progressbar', $progressbarheader, $progressbarheaderdesc));

    $progressbarenabled = [
        'name' => 'eliswidget_learningplan/progressbarenabled',
        'visiblename' => get_string('setting_progressbar_enabled', 'eliswidget_learningplan'),
        'description' => get_string('setting_progressbar_enableddescription', 'eliswidget_learningplan'),
        'defaultsetting' => 1
    ];
    $settings->add(new \admin_setting_configcheckbox($progressbarenabled['name'], $progressbarenabled['visiblename'],
            $progressbarenabled['description'], $progressbarenabled['defaultsetting']));

    $defaultcolors = ['#A70505', '#D5D100', '#009029'];
    for ($i = 1; $i <= 3; $i++) {
        $progressbarcolor = [
            'name' => 'eliswidget_learningplan/progressbarcolor'.$i,
            'visiblename' => get_string('setting_progressbar_color'.$i, 'eliswidget_learningplan'),
            'description' => get_string('setting_progressbar_color'.$i.'description', 'eliswidget_learningplan'),
            'defaultsetting' => $defaultcolors[$i-1]
        ];
        $settings->add(new \admin_setting_configcolourpicker($progressbarcolor['name'], $progressbarcolor['visiblename'],
                $progressbarcolor['description'], $progressbarcolor['defaultsetting']));
    }

}
