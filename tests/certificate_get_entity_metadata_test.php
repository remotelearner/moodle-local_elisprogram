<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../eliscore/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');

// Libs.
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/certificatesettings.class.php'));
require_once(elispm::lib('data/certificateissued.class.php'));
require_once(elispm::lib('data/instructor.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('certificate.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * PHPUnit test to retrieve a user's certificates
 * @group local_elisprogram
 */
class certificate_get_entity_metadata_testcase extends elis_database_test {

    /**
     * Load PHPUnit test data.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            student::TABLE => elispm::file('tests/fixtures/class_enrolment.csv'),
            pmclass::TABLE => elispm::file('tests/fixtures/class.csv'),
            user::TABLE => elispm::file('tests/fixtures/pmuser.csv'),
            certificatesettings::TABLE => elispm::file('tests/fixtures/certificate_settings.csv'),
            certificateissued::TABLE => elispm::file('tests/fixtures/certificate_issued.csv'),
            instructor::TABLE => elispm::file('tests/fixtures/instructor.csv'),
            course::TABLE => elispm::file('tests/fixtures/pmcourse.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * This function will setup a fake certificatesettings object
     * @return object An stdClass object mimicking the properties of a certificatesettings object
     */
    protected function setup_dummy_certsettings_object() {
        $certsetting = new stdClass();
        $certsetting->id = 1;
        $certsetting->entity_id = 1;
        $certsetting->entity_course = 'fake course';
        $certsetting->cert_border = 'fake border';
        $certsetting->cert_seal = 'fake seal';
        $certsetting->cert_template = 'fake template';
        $certsetting->timecreated = 0;
        $certsetting->timemodified = 0;
        return $certsetting;
    }

    /**
     * This function will setup a fake certificateissued object
     * @return object An stdClass object mimicking the properties of a certificateissued object
     */
    protected function setup_dummy_certissued_object() {
        $certissued = new stdClass();
        $certissued->id = 1;
        $certissued->cm_userid = 1;
        $certissued->cert_setting_id = 1;
        $certissued->cert_code = 'fake code';
        $certissued->timeissued = 0;
        $certissued->timecreated = 0;
        return $certissued;
    }

    /**
     * This function will setup a fake user object
     * @return object An stdClass object mimicking some properties of a user object
     */
    protected function setup_dummy_user_object() {
        $user = new stdClass();
        $user->id = 1;
        $user->firstname = 'fake first name';
        $user->lastname = 'fake last name';
        $user->idnumber = 'fake idnumber';

        return $user;
    }

    /**
     * Data provider using invalid arguments
     * @return array An array of objects
     */
    public function incorrect_object_types_provider() {
        return array(
                array(false, false, false),
                array(1, 1, 1),
                array('one', 'two', 'three'),
                array(new stdClass(), new stdClass(), new stdClass()),
                array(new certificatesettings(), new stdClass(), new stdClass()),
                array(new certificatesettings(), new certificateissued(), new stdClass()),
                array(new certificatesettings(), new stdClass(), new user()),
                array(new stdClass(), new certificateissued(), new stdClass()),
                array(new stdClass(), new certificateissued(), new user()),
                array(new certificatesettings(), new certificateissued(), new stdClass()),
                array(new certificatesettings(), new stdClass(), new user()),
                array(new stdClass(), new certificateissued(), new user()),
        );
    }

    /**
     * Test retrieving metadata passing incorrect class instances
     * @param bool|int|string|object $certsetting Incorrect objects
     * @param bool|int|string|object $certissued Incorrect objects
     * @param bool|int|string|object $student Incorrect objects
     * @dataProvider incorrect_object_types_provider
     */
    public function test_retireve_metadata_for_entity_incorrect_objects($certsetting, $certissued, $student) {
        $result = certificate_get_entity_metadata($certsetting, $certissued, $student);

        $this->assertEquals(false, $result);
    }

    /**
     * Test retrieving metadata passing incorrect entity type name
     */
    public function test_retireve_metadata_for_entity_wrong_entity_type() {
        $this->load_csv_data();

        $student     = new user();
        $certissued  = new certificateissued();
        $certsetting = new certificatesettings(1);
        $certsetting->load();
        $certsetting->entity_type = '';
        $result = certificate_get_entity_metadata($certsetting, $certissued, $student);

        $this->assertEquals(false, $result);
    }

    /**
     * Test retrieving metadata passing missing entity type property
     */
    public function test_retireve_metadata_for_entity_missing_entity_type() {
        $this->load_csv_data();

        $student     = new user();
        $certissued  = new certificateissued();
        $certsetting = new certificatesettings(1);
        $certsetting->load();
        unset($certsetting->entity_type);

        $result = certificate_get_entity_metadata($certsetting, $certissued, $student);

        $this->assertEquals(false, $result);
    }

    /**
     * Test retrieving metadata passing correct entity type name
     */
    public function test_retireve_metadata_for_entity_correct_entity_type() {
        $this->load_csv_data();

        $student     = new user(104);
        $certsetting = new certificatesettings(6);
        $certissued  = new certificateissued(9);
        $certissued->load();
        $student->load();
        $certsetting->load();

        $result = certificate_get_entity_metadata($certsetting, $certissued, $student);

        $this->assertNotEquals(false, $result);
    }

    /**
     * Test retrieving course metadata
     */
    public function test_retrieve_metadata_for_course_entity() {
        $this->load_csv_data();

        $student     = new user(104);
        $certsetting = new certificatesettings(6);
        $certissued  = new certificateissued(9);
        $certissued->load();
        $student->load();
        $certsetting->load();

        $result = certificate_get_course_entity_metadata($certsetting, $certissued, $student);

        $expected = array(
            'student_name' => 'User Test2',
            'course_name' => 'Test Course',
            'class_idnumber' => 'Test_Class_Instance_1',
            'class_enrol_time' => 1358315400,
            'class_startdate' => 0,
            'class_enddate' => 0,
            'class_grade' => '10.00000',
            'cert_timeissued' => 1358363100,
            'cert_code' => '339Fjap8j6oPKnw',
            'class_instructor_name' => 'User Test1'
        );

        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider using invalid arguments
     * @return array An array of objects
     */
    public function incorrect_object_properties_provider() {
        $certsetting = $this->setup_dummy_certsettings_object();
        $certissued  = $this->setup_dummy_certissued_object();
        $student     = $this->setup_dummy_user_object();

        $data = array();

        // Incorrect student id.
        $student->id = 999;
        $certsetting->entity_id = 100;
        $certissued->timeissued = 1358363100;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Incorrect certsetting entity id.
        $student->id = 104;
        $certsetting->entity_id = 999;
        $certissued->timeissued = 1358363100;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Incorrect certissued timeissued.
        $student->id = 104;
        $certsetting->entity_id = 100;
        $certissued->timeissued = 999;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Missing student id property.
        unset($student->id);
        $certsetting->entity_id = 100;
        $certissued->timeissued = 1358363100;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Missing certsetting entity_id property.
        $student->id = 104;
        unset($certsetting->entity_id);
        $certissued->timeissued = 1358363100;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Missing certsetting entity_id property.
        $student->id = 104;
        $certsetting->entity_id = 100;
        unset($certissued->timeissued);
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        return $data;
    }

    /**
     * Test retrieving course metadata with incorrect object properties
     * @param object $certsetting Certificate settings mock object
     * @param object $certissued Certificate issued mock object
     * @param object $student User mock object
     * @dataProvider incorrect_object_properties_provider
     */
    public function test_retrieve_metadata_for_course_entity_incorrect_object_properties($certsetting, $certissued, $student) {
        $this->load_csv_data();

        $result = certificate_get_course_entity_metadata($certsetting, $certissued, $student);
        phpunit_util::reset_debugging();
        $this->assertEquals(false, $result);
    }

    /**
     * Test retrieving program metadata.
     */
    public function test_retrieve_metadata_for_program_entity() {
        global $CFG;
        require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');
        require_once(elispm::lib('data/usertrack.class.php'));
        $certdateformat = get_string('pm_certificate_date_format', 'local_elisprogram'); // Win?
        $curdate = time();
        $olddate = $curdate - 300000; // arb.
        $ins = [];
        $users = [[], [], [], []];
        foreach ($users as $key => $user) {
            $user['username'] = 'testuser'.$key;
            $user['idnumber'] = 'testuser'.$key;
            $user['firstname'] = 'Test';
            $user['lastname'] = 'User'.$key;
            $user['email'] = 'tu'.$key.'@noreply.com';
            $user['city'] = '*';
            $user['country'] = 'CA';
            $newuser = new user($user);
            $newuser->save();
            $users[$key]['id'] = $newuser->id;
            if ($key) {
                $ins[] = $newuser->moodle_fullname();
            }
        }
        $courses = [[], []];
        foreach ($courses as $key => $course) {
            $cdnum = $key + 1;
            $course['name'] = "CD-{$cdnum} Name";
            $course['code'] = "CD-{$cdnum} Code";
            $course['idnumber'] = "CD-{$cdnum} ID";
            $course['syllabus'] = "CD-{$cdnum} Syllabus";
            $course['credits'] = 0.755;
            $course['completion_grade'] = 55;
            $newcrs = new course($course);
            $newcrs->save();
            $courses[$key]['id'] = $newcrs->id;
        }
        $program = new curriculum([
            'idnumber' => 'PRG-1 ID',
            'name' => 'PRG-1 Name',
            'description' => 'PRG-1 Description',
            'reqcredits' => 1.51,
            'priority' => 3,
        ]);
        $program->save();
        $track = new track([
            'curid' => $program->id,
            'idnumber' => 'TRK-PRG-1 ID',
            'name' => 'TRK-PRG-1 Name',
            'description' => 'TRK-PRG-1 Description',
        ]);
        $track->save();
        $classes = [[], []];
        foreach ($classes as $key => $pmclass) {
            $cinum = $key + 1;
            $pmclass['idnumber'] = 'CI-'.$cinum.'01';
            $pmclass['courseid'] = $courses[$key]['id'];
            $newclass = new pmclass($pmclass);
            $newclass->save();
            $classes[$key]['id'] = $newclass->id;
            $trkass = new trackassignment([
                'trackid' => $track->id,
                'classid' => $newclass->id,
                'courseid' => $courses[$key]['id']
            ]);
            $trkass->save();
            foreach ($users as $ukey => $user) {
                if ($ukey) { // instructor
                    $instructor = new instructor(['classid' => $newclass->id, 'userid' => $user['id']]);
                    $instructor->save();
                } else { // student: passes, 90.87, passed, locked
                    $stu = new student([
                        'classid' => $newclass->id,
                        'userid' => $user['id'],
                        'enrolmenttime' => $olddate,
                        'completetime' => $curdate,
                        'completestatusid' => 2,
                        'grade' => 90.87,
                        'credits' => 0.755,
                        'locked' => 1
                    ]);
                    $stu->save();
                }
            }
        }
        $usrtrk = new usertrack(['userid' => $users[0]['id'], 'trackid' => $track->id]);
        $usrtrk->save();
        $curass = new curriculumstudent(['userid' => $users[0]['id'], 'curriculumid' => $program->id]);
        $curass->save();
        $curass->complete($curdate, 1.51, true);
        $stu->users->load();
        $expected = [
            'program_idnumber' => $program->idnumber,
            'program_name' => $program->name,
            'program_description' => $program->description,
            'program_reqcredits' => $program->reqcredits,
            'program_iscustom' => 0,
            'program_timetocomplete' => $program->timetocomplete,
            'program_frequency' => $program->frequency,
            'program_priority' => $program->priority,
            'person_fullname' => $stu->users->moodle_fullname(),
            'entity_name' => $program->__toString(),
            'certificatecode' => $curass->certificatecode,
            'curriculum_frequency' => $program->frequency,
            'datecomplete' => userdate($curass->timecompleted, $certdateformat),
            'date_string' => userdate($curass->timecompleted, $certdateformat),
            'expirydate' => !empty($curass->timeexpired) ? userdate($curass->timeexpired, $certdateformat) : '',
            'track_idnumber' => $track->idnumber,
            'track_name' => $track->name,
            'track_description' => $track->description,
            'instructors' => implode(', ', array_unique($ins))
        ];
        $result = certificate_get_program_entity_metadata($curass);
        foreach ($expected as $key => $val) {
            $this->assertEquals($val, $result[$key]);
        }
    }
}
