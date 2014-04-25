<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once(dirname(__FILE__).'/../../eliscore/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');

// Data objects.
require_once(elispm::lib('data/courseset.class.php'));

/**
 * Test courseset data object.
 * @group local_elisprogram
 */
class courseset_testcase extends elis_database_test {

    /**
     * Courseset creation data provider
     */
    public function courseset_create_dataprovider() {
        return array(
                array(array( // valid
                    'idnumber' => '12345678',
                    'name' => 'Courseset Name',
                    'description' => 'Courseset Description',
                    'priority' => '1'), true
                ),
                array(array( // invalid - empty idnumber
                    'idnumber' => '',
                    'name' => 'Courseset Name',
                    'description' => 'Courseset Description',
                    'priority' => '1'), false
                ),
                array(array( // invalid - idnumber over max. length
                    'idnumber' => '123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890',
                    'name' => 'Courseset Name',
                    'description' => 'Courseset Description',
                    'priority' => '1'), false
                ),
        );
    }

    /**
     * Test courseset creation
     * @param array $inputdata the input data for courseset creation
     * @param bool $expected true if courseset should exist, false otherwise
     * @dataProvider courseset_create_dataprovider
     */
    public function test_courseset_create($inputdata, $expected) {
        $courseset = new courseset($inputdata);
        $exists = !empty($courseset);
        if ($exists) {
            try {
                $courseset->save();
            } catch (Exception $e) {
                $exists = false;
            }
        }
        $this->assertEquals($expected, $exists);
    }

    /**
     * Test courseset cannot have duplicate idnumber
     */
    public function test_courseset_duplicate_idnumber() {
        $crsset1 = array(
            'idnumber' => '123456789',
            'name' => 'Courseset1 Name',
            'description' => 'Courseset1 Description',
            'priority' => '1'
        );
        $crsset2 = array(
            'idnumber' => '123456789',
            'name' => 'Courseset2 Name',
            'description' => 'Courseset2 Description',
            'priority' => '2'
        );
        $courseset1 = new courseset($crsset1);
        $courseset1->save();
        $courseset2 = new courseset($crsset2);
        $failed = false;
        try {
            $courseset2->save();
        } catch (Exception $e) {
            $failed = true;
        }
        $this->assertTrue($failed);
    }
}
