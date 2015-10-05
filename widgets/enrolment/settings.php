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
 * @copyright  (C) 2014 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

defined('MOODLE_INTERNAL') || die;

global $USER;

if ($ADMIN->fulltree) {
    global $CFG;
    require_once($CFG->dirroot.'/local/elisprogram/lib/deepsight/lib/filter.php');
    require_once($CFG->dirroot.'/local/elisprogram/lib/deepsight/lib/filters/menuofchoices.filter.php');
    require_once($CFG->dirroot.'/local/elisprogram/lib/deepsight/lib/filters/coursestatus.filter.php');
    require_once($CFG->dirroot.'/local/elisprogram/lib/deepsight/lib/filters/classstatus.filter.php');

    $settings->add(new \admin_setting_configcheckbox('eliswidget_enrolment/syncusergrades', get_string('setting_syncusergrades', 'eliswidget_enrolment'),
            get_string('setting_syncusergrades_description', 'eliswidget_enrolment'), 1));

    // Progress bar.
    $progressbarheader = get_string('setting_progressbar_heading', 'eliswidget_enrolment');
    $progressbarheaderdesc = get_string('setting_progressbar_heading_description', 'eliswidget_enrolment');
    $settings->add(new \admin_setting_heading('progressbar', $progressbarheader, $progressbarheaderdesc));

    $progressbarenabled = [
        'name' => 'eliswidget_enrolment/progressbarenabled',
        'visiblename' => get_string('setting_progressbar_enabled', 'eliswidget_enrolment'),
        'description' => get_string('setting_progressbar_enableddescription', 'eliswidget_enrolment'),
        'defaultsetting' => 1
    ];
    $settings->add(new \admin_setting_configcheckbox($progressbarenabled['name'], $progressbarenabled['visiblename'],
            $progressbarenabled['description'], $progressbarenabled['defaultsetting']));

    $defaultcolors = ['#A70505', '#D5D100', '#009029'];
    for ($i = 1; $i <= 3; $i++) {
        $progressbarcolor = [
            'name' => 'eliswidget_enrolment/progressbarcolor'.$i,
            'visiblename' => get_string('setting_progressbar_color'.$i, 'eliswidget_enrolment'),
            'description' => get_string('setting_progressbar_color'.$i.'description', 'eliswidget_enrolment'),
            'defaultsetting' => $defaultcolors[$i-1]
        ];
        $settings->add(new \admin_setting_configcolourpicker($progressbarcolor['name'], $progressbarcolor['visiblename'],
                $progressbarcolor['description'], $progressbarcolor['defaultsetting']));
    }

    // Enabled fields for each level.
    $enabledfieldsheader = get_string('setting_enabledfields_heading', 'eliswidget_enrolment');
    $enabledfieldsheaderdesc = get_string('setting_enabledfields_heading_description', 'eliswidget_enrolment');
    $settings->add(new \admin_setting_heading('enabledfields', $enabledfieldsheader, $enabledfieldsheaderdesc));

    $fieldlevels = [
        'curriculum' => [
            'displayname' => get_string('curriculum', 'local_elisprogram'),
            'fields' => [
                'idnumber' => [
                    'label' => get_string('curriculum_idnumber', 'local_elisprogram'),
                    'visible' => true
                ],
                'name' => [
                    'label' => get_string('curriculum_name', 'local_elisprogram'),
                    'visible' => true
                ],
                'description' => [
                    'label' => get_string('description', 'local_elisprogram'),
                    'visible' => true
                ],
                'reqcredits' => [
                    'label' => get_string('curriculum_reqcredits', 'local_elisprogram'),
                    'visible' => false
                ]
            ]
        ],
        'courseset' => [
            'displayname' => get_string('courseset', 'local_elisprogram'),
            'fields' => [
                'idnumber' => [
                    'label' => get_string('courseset_idnumber', 'local_elisprogram'),
                    'visible' => true
                ],
                'name' => [
                    'label' => get_string('courseset_name', 'local_elisprogram'),
                    'visible' => true
                ],
                'description' => [
                    'label' => get_string('description', 'local_elisprogram'),
                    'visible' => true
                ],
            ]
        ],
        'course' => [
            'displayname' => get_string('course', 'local_elisprogram'),
            'fields' => [
                'name' => [
                    'label' => get_string('course_name', 'local_elisprogram'),
                    'visible' => true
                ],
                'code' => [
                    'label' => get_string('course_code', 'local_elisprogram'),
                    'visible' => true
                ],
                'idnumber' => [
                    'label' => get_string('course_idnumber', 'local_elisprogram'),
                    'visible' => true
                ],
                'description' => [
                    'label' => get_string('course_syllabus', 'local_elisprogram'),
                    'visible' => true
                ],
                'credits' => [
                    'label' => get_string('credits', 'local_elisprogram'),
                    'visible' => true
                ],
                'cost' => [
                    'label' => get_string('cost', 'local_elisprogram'),
                    'visible' => false
                ],
                'version' => [
                    'label' => get_string('course_version', 'local_elisprogram'),
                    'visible' => false
                ],
                'coursestatus' => [
                    'label' => get_string('course_status', 'local_elisprogram'),
                    'visible' => true,
                    'datatype' => 'menu',
                    'choices' => deepsight_filter_coursestatus::get_static_choices()
                ]
            ]
        ],
        'class' => [
            'displayname' => get_string('class', 'local_elisprogram'),
            'fields' => [
                'idnumber' => [
                    'label' => get_string('class_idnumber', 'local_elisprogram'),
                    'visible' => true
                ],
                'startdate' => [
                    'label' => get_string('class_startdate', 'local_elisprogram'),
                    'datatype' => 'date',
                    'visible' => true
                ],
                'enddate' => [
                    'label' => get_string('class_enddate', 'local_elisprogram'),
                    'datatype' => 'date',
                    'visible' => true
                ],
              /** TBD: starttime & endtime never were supported!
                ,
                'starttime' => [
                    'label' => get_string('class_starttime', 'local_elisprogram'),
                    'datatype' => 'time',
                    'visible' => true
                ],
                'endtime' => [
                    'label' => get_string('class_endtime', 'local_elisprogram'),
                    'datatype' => 'time',
                    'visible' => true
                ],
              */
                'classstatus' => [
                    'label' => get_string('class_status', 'local_elisprogram'),
                    'visible' => true,
                    'datatype' => 'menu',
                    'choices' => deepsight_filter_classstatus::get_static_choices()
                ]
            ]
        ]
    ];
    foreach ($fieldlevels as $ctxlvl => $info) {
        $enabledfields = [
            'name' => 'eliswidget_enrolment/'.$ctxlvl.'_field_',
            'visiblename' => get_string('setting_enabledfields', 'eliswidget_enrolment', $info['displayname']),
            'description' => get_string('setting_enabledfields_description', 'eliswidget_enrolment', $info['displayname'])
        ];
        $settings->add(new \admin_setting_heading($enabledfields['name'], $info['displayname'], '')); // TBD.
        foreach ($info['fields'] as $ckey => $cval) {
            $settings->add(new \local_elisprogram\admin\setting\widgetfilterconfig($enabledfields['name'].$ckey, $cval['label'],
                    '', isset($cval['datatype']) ? $cval['datatype'] : '' , (isset($cval['visible']) && $cval['visible'] == false) ? 1 : 0,
                    isset($cval['choices']) ? $cval['choices'] : []));
        }

        // Get custom fields ...
        if (($fields = field::get_for_context_level($ctxlvl)) && $fields->valid()) {
            foreach ($fields as $field) {
                $manual = null;
                $name = $enabledfields['name'].strtolower('cf_'.$field->shortname);
                if ($field->datatype == 'bool') {
                    $datatype = 'bool';
                } else {
                    $manual = new field_owner($field->owners['manual']);
                    switch ($manual->param_control) {
                        case 'menu':
                            $datatype = 'menu';
                            break;
                        case 'datetime':
                            $datatype = 'date';
                            break;
                        default:
                            $datatype = 'text';
                    }
                }
                $settings->add(new \local_elisprogram\admin\setting\widgetfilterconfig($name, $field->name, $field->description, $datatype, 1,
                        ($datatype == 'menu' && !empty($manual)) ? $manual->get_menu_options() : []));
            }
        }
    }

}
