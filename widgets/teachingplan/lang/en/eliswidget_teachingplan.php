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

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'TeachingPlan Widget';
$string['name'] = 'ELIS Teaching Plan Widget';
$string['description'] = 'A widget allowing instructors to view their teaching plans.';

$string['class_listing_heading'] = 'Class Listing settings';
$string['class_listing_heading_description'] = 'Settings to control the Class Instance listing table columns.';
$string['class_table_class_header'] = 'Class/Moodle&nbsp;Course';
$string['class_table_class_cell'] = '<a href=\'{$a->wwwroot}/local/elisprogram/index.php?s=cls&action=view&id={$a->element_id}\'>{$a->idnumber}</a><br/>{$a->conditional_moodlecourse}<a href=\'{$a->wwwroot}/course/view.php?id={$a->moodlecourseid}\'>{$a->moodlecoursefullname}</a>';
$string['classtime'] = 'Class time';
$string['classes'] = 'Classes';
$string['column_classtime'] = 'Class time';
$string['column_classtime_description'] = 'Include <i>Class time</i> column.';
$string['column_enddate'] = 'End date';
$string['column_enddate_description'] = 'Include <i>End date</i> column.';
$string['column_enrolledstudents'] = 'Enrolled (waiting)';
$string['column_enrolledstudents_description'] = 'Include <i>Enrolled (waiting)</i> column.';
$string['column_instructors'] = 'Instructors';
$string['column_instructors_description'] = 'Include <i>Instructors</i> column.';
$string['column_maxstudents'] = 'Max students';
$string['column_maxstudents_description'] = 'Include <i>Max students</i> column.';
$string['column_moodletime'] = 'Moodle Course start time';
$string['column_moodletime_description'] = 'Include <i>Moodle Course start time</i> column.';
$string['column_startdate'] = 'Start date';
$string['column_startdate_description'] = 'Include <i>Start date</i> column.';
$string['conditional_moodlecourse'] = 'Moodle Course: ';
$string['course'] = 'Course';
$string['course_credits'] = 'Credits';
$string['course_description'] = 'Description';
$string['course_header'] = '{$a->idnumber}: {$a->name}';
$string['course_idnumber'] = 'ID Number';
$string['course_name'] = 'Name';
$string['courses'] = 'Courses';
$string['courseset_format'] = '<br/>Course Set: {$a}';
$string['coursesets'] = 'Course Sets:';
$string['data_completiongrade'] = 'Completion grade:';
$string['data_credits'] = 'Credits:';
$string['data_description'] = 'Description:';
$string['data_name'] = 'Name:';
$string['date_format'] = '%b %d, %Y';
$string['date_na'] = 'N/A';
$string['data_programs'] = 'Programs:';
$string['enddate'] = 'End Date';
$string['enrolled'] = 'Enrolled / In&nbsp;progress (waiting)';
$string['generatortitle'] = 'Add A Filter';
$string['hide_classes'] = 'Hide Classes';
$string['idnumber'] = 'ID Number';
$string['instructors'] = 'Instructors';
$string['less'] = '...less';
$string['maxstudents'] = 'Max students';
$string['moodlecourse_header'] = '{$a->coursefullname} ({$a->courseidnumber})';
$string['moodletime'] = 'Moodle Course start';
$string['more'] = 'more...';
$string['na'] = 'N/A';
$string['nonefound'] = 'None found';
$string['of'] = 'of';
$string['programs'] = 'Programs';
$string['setting_progressbar_heading'] = 'Progress Bar';
$string['setting_progressbar_heading_description'] = 'The Progress bar for Course Descriptions showing percentage of students complete. The following settings deal with the progress bar.';
$string['setting_progressbar_enabled'] = 'Enabled';
$string['setting_progressbar_enableddescription'] = 'Turn the progress bar on or off completely.';
$string['setting_progressbar_color1'] = '0%-49% Color';
$string['setting_progressbar_color1description'] = 'The progress bar will be this color if progress is between 0% and 49%.';
$string['setting_progressbar_color2'] = '50%-79% Color';
$string['setting_progressbar_color2description'] = 'The progress bar will be this color if progress is between 50% and 79%.';
$string['setting_progressbar_color3'] = '80%-100% Color';
$string['setting_progressbar_color3description'] = 'The progress bar will be this color if progress is between 80% and 100%.';
$string['show_classes'] = 'Show Classes';
$string['startdate'] = 'Start Date';
$string['teachingplan:addwidget'] = 'Add the TeachingPlan widget.';
$string['timerange'] = ' to ';
$string['waiting'] = 'waiting';
$string['working'] = 'Working...';
