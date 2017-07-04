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
 * @copyright  (C) 2008-2017 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
if (!isset($_SERVER['REMOTE_ADDR'])) {
    define('CLI_SCRIPT', true);
}

require_once(dirname(__FILE__) .'/../lib/setup.php');
require_once(elispm::lib('data/curriculumcourse.class.php'));
global $CFG, $DB;

if (isset($_SERVER['REMOTE_ADDR'])) {
    die("Cannot execute this script via web, command-line only.\n");
}
$usage = 'Usage: '.basename(__FILE__)." [--debug] [programid]\n";
if ($argc > 3) {
    die($usage);
}
$prgid = false;
$debug = false;
for ($i = $argc - 1; $i; --$i) {
    switch ($argv[$i]) {
        case '--debug':
            $debug = true;
            break;
        default:
            if (!$prgid && is_numeric($argv[$i]) && curriculum::exists(new field_filter('id', $argv[$i]))) {
                $prgid = $argv[$i];
            } else {
                die($usage);
            }
            break;
    }
}

if (!$prgid && empty($CFG->maintenance_enabled)) {
    die("You must put site into Maintenance Mode to update all Programs, or select a single Program to update.\n");
}
mtrace('Begin Program Completion Date updating ...');
$fixedcnt = 0;
$skippedcnt = 0;

$clsenrolafterprgenrol = !empty(elis::$config->local_elisprogram->clsenrolafterprgenrol);
$currs = $DB->get_recordset(curriculum::TABLE, $prgid ? ['id' => $prgid] : null);
foreach ($currs as $cur) {
    // Get all courses in program, including courseset courses.
    $currcrssql = 'SELECT DISTINCT crs.id
                     FROM {'.course::TABLE.'} crs
                LEFT JOIN {'.curriculumcourse::TABLE.'} curcrs ON curriculumid = ?
                          AND courseid = crs.id
                LEFT JOIN {'.programcrsset::TABLE.'} pcs ON pcs.prgid = ?
                LEFT JOIN {'.crssetcourse::TABLE.'} csc ON pcs.crssetid = csc.crssetid
                          AND crs.id = csc.courseid
                    WHERE curcrs.id IS NOT NULL
                          OR (pcs.id IS NOT NULL AND csc.id IS NOT NULL)';
    $currcourses = $DB->get_records_sql($currcrssql, [$cur->id, $cur->id]);
    if ($debug) {
        mtrace("\ncur: {$cur->id} - courses = ".var_sdump($currcourses));
    }
    if (empty($currcourses)) {
        continue;
    }
    list($crsinorequal, $crsparams) = $DB->get_in_or_equal(array_keys($currcourses));
    $ctsql = 'SELECT MAX(stu.completetime) AS completetime, SUM(stu.credits) AS totalcredits
                FROM {'.student::TABLE.'} stu
                JOIN {'.pmclass::TABLE."} cls ON stu.classid = cls.id
               WHERE cls.courseid {$crsinorequal}
                     AND stu.userid = ?
                     AND stu.completestatusid = ?
                     AND stu.locked = 1
                     AND stu.completetime > 0";
    if ($clsenrolafterprgenrol) {
        $ctsql .= ' AND stu.enrolmenttime >= ?';
    }
    // Loop over all users completed in program.
    $currasses = $DB->get_recordset_select('local_elisprogram_pgm_assign',
            'curriculumid = ? AND locked = 1 AND completed = ? AND timecompleted > 0', [$cur->id, STUSTATUS_PASSED]);
    foreach ($currasses as $currass) {
        $ctparams = [$currass->userid, STUSTATUS_PASSED];
        if ($clsenrolafterprgenrol) {
            $ctparams[] = $currass->timecreated;
        }
        $data = $DB->get_record_sql($ctsql, array_merge($crsparams, $ctparams));
        if (!empty($data->completetime) && $data->completetime <= $currass->timecompleted) {
            if ($debug) {
                mtrace("Updating {$currass->userid} completetime from {$currass->timecompleted} to {$completetime}");
            }
            $currass->timecompleted = $data->completetime;
            if ($data->totalcredits != $currass->credits && $data->totalcredits >= $cur->reqcredits) {
                $currass->credits = $data->totalcredits;
            }
            $DB->update_record('local_elisprogram_pgm_assign', $currass);
            $fixedcnt++;
        } else {
            $skippedcnt++;
        }
    }
}

if ($fixedcnt > 0) {
    mtrace("\n{$fixedcnt} Program Completion dates updated. ({$skippedcnt} skipped)");
} else {
    mtrace("\nNo Program assignments to update where found! ({$skippedcnt} skipped)");
}

exit;

function var_sdump($var) {
    ob_start();
    var_dump($var);
    $tmp = ob_get_contents();
    ob_end_clean();
    return $tmp;
}

