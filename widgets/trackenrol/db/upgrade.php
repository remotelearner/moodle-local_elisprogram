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
 * ELIS Widget Track Enrolment upgrade.
 * @param string|int $oldversion
 * @return bool true on success, false on error.
 */
function xmldb_eliswidget_trackenrol_upgrade($oldversion = 0) {
    global $DB;
    $result = true;

    if ($result && $oldversion < 2014082501) {
        // Convert allowed usersets setting from multi-select drop-down to new tree-selector
        $allowedusersets = get_config('eliswidget_trackenrol', 'viewusersets');
        if (!empty($allowedusersets)) {
            $viewusersetstree = [
                'cluster_unexpanded' => '',
                'cluster_clrunexpanded' => '',
                'cluster_usingdropdown' => '0',
                'cluster_dropdown' => '0'
            ];
            $uses = [];
            $dropdown = false;
            foreach (explode(',', $allowedusersets) as $usids) {
                if (empty($usids)) {
                    $dropdown = true;
                    break;
                }
                $usidarray = @unserialize($usids);
                if (!empty($usidarray) && is_array($usidarray)) {
                    $id = $usidarray[0];
                    $parentpath = '';
                    $parentid = $id;
                    while (($parentid = $DB->get_field('local_elisprogram_uset', 'parent', array('id' => $parentid)))) {
                        $parentpath = 'userset-'.$parentid.'/'.$parentpath;
                    }
                    $uses[] = "userset_{$id}_{$id}_0_{$parentpath}userset-{$id}";
                }
            }
            if ($dropdown || empty($uses)) {
                $viewusersetstree['cluster_usingdropdown'] = '1';
                $viewusersetstree['cluster_listing'] = '';
            } else {
                $viewusersetstree['cluster_listing'] = implode(',', $uses);
            }
            set_config('viewusersetstree', serialize($viewusersetstree), 'eliswidget_trackenrol');
        }
        unset_config('viewusersets', 'eliswidget_trackenrol');
        upgrade_plugin_savepoint($result, '2014082501', 'eliswidget', 'trackenrol');
    }

    return $result;
}
