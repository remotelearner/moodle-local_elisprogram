<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2017 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2017 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

if (isset($_SERVER['REMOTE_ADDR'])) {
    die("No web access, use command-line.\n");
}
$usage = 'Usage: '.basename(__FILE__)." [--all] [--after <\"strtotime string\">] [--userid <ELISuserid>] [--programid <ELISprogramid>]\n";
if ($argc > 7) {
    die($usage);
}

define('CLI_SCRIPT', true);
require_once(dirname(__FILE__).'/../lib/setup.php');
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
global $DB;

$all = false;
$prgid = false;
$userid = false;
$after = false;
$val = 'z'; // Not numeric.
for ($i = $argc - 1; $i; --$i) {
    switch ($argv[$i]) {
        case '--userid':
            if (!is_numeric($val) || !user::exists([new field_filter('id', $val)])) {
                die($usage);
            }
            $userid = $val;
            $val = 'z';
            break;
        case '--programid':
            if (!is_numeric($val) || !curriculum::exists([new field_filter('id', $val)])) {
                die($usage);
            }
            $prgid = $val;
            $val = 'z';
            break;
        case '--after':
            $timestamp = strtotime($val);
            if (empty($timestamp)) {
                die($usage);
            }
            $after = $timestamp;
            $val = 'z';
            break;
        case '--all':
            if ($val != 'z' || $i != 1) {
                die($usage);
            }
            $all = true;
            break;
        default:
            if ($val != 'z' || $i == 1) {
                die($usage);
            }
            $val = $argv[$i];
            break;
    }
}
if (!$all && !$after && empty($userid) && empty($prgid)) {
    die($usage);
}

$curstuselect = 'completed = :completed';
$params = ['completed' => STUSTATUS_PASSED];
if (!empty($userid)) {
    $curstuselect .= ' AND userid = :userid';
    $params['userid'] = $userid;
}
if (!empty($prgid)) {
    $curstuselect .= ' AND curriculumid = :curriculumid';
    $params['curriculumid'] = $prgid;
}
if (!empty($after)) {
    $curstuselect .= ' AND timecreated >= :timecreated';
    $params['timecreated'] = $after;
}
mtrace('Resetting Program completions ...');
$cnt = 0;
$curstus = $DB->get_recordset_select(curriculumstudent::TABLE, $curstuselect, $params);
foreach ($curstus as $curstu) {
    ++$cnt;
    $curstu->completed = STUSTATUS_NOTCOMPLETE;
    $curstu->timecompleted = 0;
    $curstu->credits = 0;
    $curstu->locked = 0;
    $DB->update_record(curriculumstudent::TABLE, $curstu);
}

mtrace("Done - reset {$cnt} ELIS Program completions.");
if (!empty($userid)) {
    mtrace("Moodle userid = ".$DB->get_field(usermoodle::TABLE, 'muserid', ['cuserid' => $userid]));
}
