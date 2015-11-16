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
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

require_once(__DIR__.'/../../../../eliscore/test_config.php');
global $CFG;

require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');

/**
 * Test \eliswidget_learningplan\widget.
 * @group eliswidget_learningplan
 */
class learningplan_widget_testcase extends \elis_database_test {
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
     * Test the get_program_data function.
     */
    public function test_get_program_data() {
        global $CFG, $DB, $USER;
        $ajax = new \eliswidget_learningplan\ajax($CFG->wwwroot); // TBD: arbitrary?

        $datagen = $this->getelisdatagenerator();
        // Create user. $datagen->create_user();
        require_once(\elispm::lib('lib.php'));
        $mockuser1 = get_test_user('mockuser1');
        $mockuser2 = get_test_user('mockuser2');
        pm_migrate_moodle_users(true);
        $elisuser1 = pm_get_crlmuserid($mockuser1->id);
        $elisuser2 = pm_get_crlmuserid($mockuser2->id);

        $program1 = $datagen->create_program();
        $datagen->assign_user_to_program($elisuser1, $program1->id);
        $program2 = $datagen->create_program();
        $datagen->assign_user_to_program($elisuser1, $program2->id);
        $program3 = $datagen->create_program();
        $datagen->assign_user_to_program($elisuser2, $program3->id);
        $program4 = $datagen->create_program();
        $data = [
            'filters'     => (object)[],
            'initialized' => false,
            'page'        => 1,
            'widgetid'    => 19, // TBD: arbitrary?
            'm'           => 'programsforuser'
        ];
        $USER = $mockuser1;
        $programs = $ajax->get_programsforuser($data);

        // Convert recordset to array.
        $programsar = [];
        foreach ($programs['children'] as $program) {
            $programsar[$program->id] = $program;
        }

        // Validate.
        $this->assertEquals(2, count($programsar));

        $USER = $mockuser2;
        $programs = $ajax->get_programsforuser($data);

        // Convert recordset to array.
        $programsar = [];
        foreach ($programs['children'] as $program) {
            $programsar[$program->id] = $program;
        }

        // Validate.
        $this->assertEquals(1, count($programsar));
    }
}
