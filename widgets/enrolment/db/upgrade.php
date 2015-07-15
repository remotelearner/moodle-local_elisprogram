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
 *
 */

defined('MOODLE_INTERNAL') || die();

/**
 * ELIS Program Enrolment upgrade.
 * @param string|int $oldversion
 * @return bool true on success, false on error.
 */
function xmldb_eliswidget_enrolment_upgrade($oldversion = 0) {
    global $DB;
    $result = true;

    if ($result && $oldversion < 2014082502) {
        // Convert enabled fields multiselect to separate radio buttons: 0 => visible, 1 => hidden
        $fields = [
            'curriculum' => ['idnumber', 'name', 'description', 'reqcredits'],
            'courseset' => ['idnumber', 'name', 'description'],
            'course' => ['name', 'code', 'idnumber', 'description', 'credits', 'cost', 'version'],
            'class' => ['idnumber', 'startdate', 'enddate', 'starttime', 'endtime']
        ];
        foreach ($fields as $ctxlvl => $allfields) {
            if (($curval = get_config('eliswidget_enrolment', $ctxlvl.'enabledfields'))) {
                $enabledfields = explode(',', $curval);
                if (($customfields = field::get_for_context_level($ctxlvl)) && $customfields->valid()) {
                    foreach ($customfields as $customfield) {
                        $allfields[] = strtolower('cf_'.$customfield->shortname);
                    }
                }
                foreach ($allfields as $field) {
                    set_config($ctxlvl.'_field_'.$field.'_radio', in_array($field, $enabledfields) ? 0 : 1, 'eliswidget_enrolment');
                }
                unset_config($ctxlvl.'enabledfields', 'eliswidget_enrolment');
            }
        }
        upgrade_plugin_savepoint($result, '2014082502', 'eliswidget', 'enrolment');
    }

    return $result;
}
