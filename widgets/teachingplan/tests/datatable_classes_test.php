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

require_once(__DIR__.'/../../../../eliscore/test_config.php');
global $CFG;

require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');

/**
 * Test \eliswidget_teachingplan\datatable\classes
 * @group eliswidget_teachingplan
 */
class datatable_classes_testcase extends \elis_database_test {
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
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    protected function setup_tables() {
        global $CFG;
        require_once(\elispm::lib('data/course.class.php'));
        require_once(\elispm::lib('data/curriculum.class.php'));
        require_once(\elispm::lib('data/curriculumcourse.class.php'));
        require_once(\elispm::lib('data/user.class.php'));
        require_once(\elispm::lib('data/usermoodle.class.php'));
        require_once(\elispm::lib('data/student.class.php'));
        require_once(\elispm::lib('data/waitlist.class.php'));
        $dir = dirname(__FILE__);
        $dataset = $this->createCsvDataSet([
            curriculum::TABLE => $dir.'/fixtures/program.csv',
            course::TABLE => $dir.'/fixtures/pmcourse.csv',
            curriculumcourse::TABLE => $dir.'/fixtures/prgcourse.csv',
            pmclass::TABLE => $dir.'/fixtures/pmclass.csv',
            user::TABLE => $dir.'/fixtures/user.csv',
            'user' => $dir.'/fixtures/user.csv',
            usermoodle::TABLE => $dir.'/fixtures/usermoodle.csv',
            student::TABLE => $dir.'/fixtures/student.csv',
            waitlist::TABLE => $dir.'/fixtures/waitlist.csv',
        ]);
        $this->loadDataSet($dataset);
    }

    /**
     * Test get_search_results function.
     */
    public function test_get_search_results() {
        global $DB;

        // Init. DB tables.
        $this->setup_tables();

        $datagen = $this->getelisdatagenerator();

        set_config('instructors', 1, 'eliswidget_teachingplan');

        // Create entitie(s).
        $mockuser = $datagen->create_user(['firstname' => 'Test', 'lastname' => 'Instructor1']);

        // Run method.
        $datatable = new \eliswidget_teachingplan\datatable\classes($DB, 'http://example.com');
        $datatable->set_userid($mockuser->id);
        $datatable->set_courseid(101);
        list($classes, $totalresults) = $datatable->get_search_results();

        // Validate.
        $this->assertEquals(0, $totalresults);
        $this->assertEquals(0, count($classes));

        // Perform assignments.
        $ins1 = $datagen->assign_instructor_to_class($mockuser->id, 101);

	// Run method.
	list($classes, $totalresults) = $datatable->get_search_results();

        // Validate.
        $this->assertEquals(1, $totalresults);
        $cls = current($classes);
        $this->assertEquals('3', (string)$cls->maxstudents);
        $this->assertEquals('4 / 2 (1)', $cls->enrolled);
        $this->assertTrue(strpos($cls->instructors, 'Test Instructor1') !== false);

        $ins2 = $datagen->assign_instructor_to_class($mockuser->id, 102);
        $ins3 = $datagen->assign_instructor_to_class($mockuser->id, 104);
        $ins5 = $datagen->assign_instructor_to_class($mockuser->id, 105);

	// Run method.
	list($classes, $totalresults) = $datatable->get_search_results();

        // Validate.
        $this->assertEquals(3, $totalresults);
        foreach ($classes as $cls) {
            if ($cls->element_id != 101) {
                $this->assertEquals('0 / 0', $cls->enrolled);
                $this->assertTrue(strpos($cls->instructors, 'Test Instructor1') !== false);
            }
        }
    }
}
