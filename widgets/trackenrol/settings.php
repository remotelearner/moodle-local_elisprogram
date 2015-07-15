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
 * @copyright  (C) 2014 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

defined('MOODLE_INTERNAL') || die;

global $DB, $PAGE, $USER;

if ($ADMIN->fulltree) {

    // Enabled fields for each level.
    $enabledfieldsheader = get_string('setting_enabledfields_heading', 'eliswidget_trackenrol');
    $enabledfieldsheaderdesc = get_string('setting_enabledfields_heading_description', 'eliswidget_trackenrol');
    $settings->add(new \admin_setting_heading('enabledfields', $enabledfieldsheader, $enabledfieldsheaderdesc));

    $fieldlevels = [
        'track' => [
            'displayname' => get_string('tracks', 'local_elisprogram'),
            'fields' => [
                'idnumber' => [
                    'label' => get_string('track_idnumber', 'local_elisprogram'),
                    'visible' => true
                ],
                'name' => [
                    'label' => get_string('track_name', 'local_elisprogram'),
                    'visible' => true
                ],
                'description' => [
                    'label' => get_string('description', 'local_elisprogram'),
                    'visible' => true
                ],
                'program' => [
                    'label' => get_string('curriculum', 'local_elisprogram'),
                    'visible' => true
                ],
                'startdate' => [
                    'label' => get_string('track_startdate', 'local_elisprogram'),
                    'datatype' => 'date',
                    'visible' => false
                ],
                'enddate' => [
                    'label' => get_string('track_enddate', 'local_elisprogram'),
                    'datatype' => 'date',
                    'visible' => false
                ]
            ]
        ]
    ];
    foreach ($fieldlevels as $ctxlvl => $info) {
        $enabledfields = [
            'name' => 'eliswidget_trackenrol/'.$ctxlvl.'_field_',
            'visiblename' => get_string('setting_enabledfields', 'eliswidget_trackenrol', $info['displayname']),
            'description' => get_string('setting_enabledfields_description', 'eliswidget_trackenrol', $info['displayname'])
        ];
        $settings->add(new \admin_setting_heading($enabledfields['name'], $info['displayname'], '')); // TBD.
        foreach ($info['fields'] as $ckey => $cval) {
            $settings->add(new \local_elisprogram\admin\setting\widgetfilterconfig($enabledfields['name'].$ckey, $cval['label'],
                    '', isset($cval['datatype']) ? $cval['datatype'] : '' , (isset($cval['visible']) && $cval['visible'] == false) ? 1 : 0, []));
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

    // Criterial to view widget
    // Multi-select list of required capabilities, '' => 'allowall'.
    $allowall = get_string('allowall', 'eliswidget_trackenrol');
    $any = get_string('any', 'eliswidget_trackenrol');
    $caps = ['' => $allowall];
    $caprs = $DB->get_recordset('capabilities');
    if ($caprs && $caprs->valid()) {
        foreach ($caprs as $key => $cap) {
            $caps[$key] = $cap->name;
        }
        $caprs->close();
    }
    $settings->add(new \admin_setting_configmultiselect('eliswidget_trackenrol/viewcap', get_string('setting_viewcap', 'eliswidget_trackenrol'),
            get_string('setting_viewcap_description', 'eliswidget_trackenrol'), [''], $caps));
    // Mutli-select list (or multi-checkbox) of valid contexts for cap. '' => 'any'
    $allowedcontexts = ['' => $any];
    $contextlevels = \local_eliscore\context\helper::get_all_levels();
    foreach ($contextlevels as $ctxlevel => $ctxclass) {
        $allowedcontexts[$ctxlevel] = $ctxclass::get_level_name();
    }
    $settings->add(new \admin_setting_configmultiselect('eliswidget_trackenrol/viewcontexts', get_string('setting_viewcontexts', 'eliswidget_trackenrol'),
            get_string('setting_viewcontexts_description', 'eliswidget_trackenrol'), [''], $allowedcontexts));

    $settings->add(new \local_elisprogram\admin\setting\usersetselect('eliswidget_trackenrol/viewusersetstree', get_string('setting_viewusersets', 'eliswidget_trackenrol'),
            get_string('setting_viewusersets_description', 'eliswidget_trackenrol'), '', ['']));

    // Criterial to filter tracks
    // Multi-select list of required capabilities, '' => 'allowall'.
    $settings->add(new \admin_setting_configselect('eliswidget_trackenrol/trackviewcap', get_string('setting_trackviewcap', 'eliswidget_trackenrol'),
            get_string('setting_trackviewcap_description', 'eliswidget_trackenrol'), '', $caps)); // TBD: default 'track_view'?
}
