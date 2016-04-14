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
 * @copyright  (C) 2015 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LearningPlan Widget';
$string['name'] = 'ELIS Learning Plan Widget';
$string['description'] = 'A widget allowing students to view their learning plans, and view their progress.';

$string['classes'] = 'Classes';
$string['course'] = 'Course';
$string['courses'] = 'Courses';
$string['courseset_format'] = '<br/>Course Set: {$a}';
$string['coursesets'] = 'CourseSets';
$string['data_completetime'] = 'Completed';
$string['data_grade'] = 'Grade';
$string['data_instructors'] = 'Instructors';
$string['data_status'] = 'Status';
$string['date_format'] = '%b %d, %Y';
$string['date_na'] = 'Not available';
$string['eliscourse_header'] = '{$a->name}';
$string['enddate'] = 'End Date';
$string['enrolled'] = 'enrolled';
$string['generatortitle'] = 'Add A Filter';
$string['hide_classes'] = 'Hide Classes';
$string['idnumber'] = 'ID Number';
$string['less'] = '...less';
$string['max'] = 'max';
$string['moodlecourse_header'] = '{$a->coursefullname} ({$a->courseidnumber})';
$string['more'] = 'more...';
$string['nonefound'] = 'None found';
$string['nonprogramcourses'] = 'Non-program courses';
$string['of'] = 'of';
$string['programs'] = 'Programs';
$string['program_description'] = 'Description';
$string['program_description_format'] = '{$a->description}';
$string['program_header'] = '{$a->idnumber}: {$a->name}';
$string['program_idnumber'] = 'ID Number';
$string['program_name'] = 'Name';
$string['program_reqcredits'] = 'Required Credits';
$string['setting_progressbar_heading'] = 'Progress Bar';
$string['setting_progressbar_heading_description'] = 'If a program has required credits set, a progress bar can be shown for students to help them track their progress. The following settings deal with the progress bar.';
$string['setting_progressbar_enabled'] = 'Enabled';
$string['setting_progressbar_enableddescription'] = 'Turn the progress bar on or off completely. If on, a program will still have to have it\'s required credits set for the progress bar to appear.';
$string['setting_progressbar_color1'] = '0%-49% Color';
$string['setting_progressbar_color1description'] = 'The progress bar will be this color if progress is between 0% and 49%.';
$string['setting_progressbar_color2'] = '50%-79% Color';
$string['setting_progressbar_color2description'] = 'The progress bar will be this color if progress is between 50% and 79%.';
$string['setting_progressbar_color3'] = '80%-100% Color';
$string['setting_progressbar_color3description'] = 'The progress bar will be this color if progress is between 80% and 100%.';
$string['setting_enabledfields_heading'] = 'Visible Fields';
$string['setting_enabledfields_heading_description'] = 'These settings control which fields/filters will be visible, hidden, defaulted(visible) or locked(hidden) at each level: Program, Course Set, Course Description and Class Instance.';
$string['setting_enabledfields'] = '{$a} Fields';
$string['setting_enabledfields_description'] = 'The selected {$a} fields will be visible and available for searching.';
$string['setting_showunenrolledclasses'] = 'Un-enrolled classes.';
$string['setting_showunenrolledclasses_description'] = ' Display un-enrolled classes.';
$string['setting_syncusergrades'] = 'Sync User\'s grades';
$string['setting_syncusergrades_description'] = 'If enabled, user\'s grades will be sync\'d when the widget is initially loaded on a page.';
$string['show_all'] = 'Show All Classes';
$string['show_classes'] = 'Show Classes';
$string['show_completed'] = 'Show Completed Classes';
$string['startdate'] = 'Start Date';
$string['status_enroled'] = 'Enrolled';
$string['status_notenroled'] = 'Not Enrolled';
$string['status_passed'] = 'Passed';
$string['status_prereqnotmet'] = 'Prerequisites not complete';
$string['status_failed'] = 'Failed';
$string['status_full'] = 'Full';
$string['status_unavailable'] = 'Unavailable';
$string['status_waitlist'] = 'On the waitlist';
$string['waiting'] = 'waiting';
$string['working'] = 'Working...';
