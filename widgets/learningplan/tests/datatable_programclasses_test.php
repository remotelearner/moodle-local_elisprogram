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

require_once(__DIR__.'/../../../../eliscore/test_config.php');
global $CFG;

require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');

/**
 * Test \eliswidget_learningplan\datatable\programclasses.
 * @group eliswidget_learningplan
 */
class datatable_programclasses_testcase extends \elis_database_test {
    /**
     * Get ELIS data generator.
     *
     * @return \elis_program_datagenerator An ELIS data generator instance.
     */
    protected function getelisdatagenerator() {
        global $DB, $CFG;
        require_once(\elispm::file('tests/other/datagenerator.php'));
        return new \elis_program_datagenerator($DB);
    }

    /**
     * Test get_search_results function.
     */
    public function test_get_search_results() {
        global $DB;

        $datagen = $this->getelisdatagenerator();

        // Create entities.
        $mockuser = $datagen->create_user();
        $pgm = $datagen->create_program();
        $pmcourse1 = $datagen->create_course();
        $pmclass1 = $datagen->create_pmclass(['courseid' => $pmcourse1->id]);
        $pmcourse2 = $datagen->create_course();
        $pmclass2 = $datagen->create_pmclass(['courseid' => $pmcourse2->id]);
        $pmcourse3 = $datagen->create_course();
        $pmclass3 = $datagen->create_pmclass(['courseid' => $pmcourse3->id]);

        // Perform assignments.
        $curcourse1 = $datagen->assign_course_to_program($pmcourse1->id, $pgm->id);
        $curcourse2 = $datagen->assign_course_to_program($pmcourse2->id, $pgm->id);
        $curcourse3 = $datagen->assign_course_to_program($pmcourse3->id, $pgm->id);
        $curstu = $datagen->assign_user_to_program($mockuser->id, $pgm->id);
        $stu1 = $datagen->assign_user_to_class($mockuser->id, $pmclass1->id);
        $stu2 = $datagen->assign_user_to_class($mockuser->id, $pmclass2->id);

        // Run method.
        $datatable = new \eliswidget_learningplan\datatable\programclasses($DB, 'http://example.com');
        $datatable->set_programid($pgm->id);
        $datatable->set_userid($mockuser->id);
        list($classes, $totalresults) = $datatable->get_search_results();

        // Convert recordset to array.
        $classar = [];
        foreach ($classes as $cls) {
            $classar[$cls->id] = $cls;
        }
        // Validate.
        $this->assertTrue(isset($classar[$curcourse1->id]));
        $this->assertTrue(isset($classar[$curcourse2->id]));
        $this->assertFalse(isset($classar[$curcourse3->id]));
        $this->assertEquals(2, count($classar));
    }
}
