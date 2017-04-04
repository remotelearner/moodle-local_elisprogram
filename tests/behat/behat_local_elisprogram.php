<?php

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException,
    Behat\Mink\Exception\DriverException as DriverException,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

class behat_local_elisprogram extends behat_base {
    /**
     * @Given /^the following ELIS users exist:$/
     */
    public function theFollowingElisUsersExist(TableNode $table) {
        global $CFG;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/user.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $user = new user();
            $user->idnumber = $datarow['idnumber'];
            $user->username = $datarow['username'];
            $user->password = hash_internal_user_password($datarow['username']);
            $user->email = empty($datarow['email']) ? $datarow['idnumber'].'@example.com' : $datarow['email'];
            $user->firstname = empty($datarow['firstname']) ? 'Student' : $datarow['firstname'];
            $user->lastname = empty($datarow['lastname']) ? 'Test' : $datarow['lastname'];
            $user->save();
        }
    }

    /**
     * Context name to level
     * @param string $contextname context name: user, userset, program, track, class, course, courseset
     * @return int the context level
     */
    public function contextname_2_level($contextname) {
        static $contextlevelmap = ['program' => 11, 'track' => 12, 'course' => 13, 'class' => 14, 'user' => 15, 'userset' => 16, 'courseset' => 17];
        return $contextlevelmap[$contextname];
    }

    /**
     * @Given /^the following ELIS custom fields exist:$/
     */
    public function theFollowingELIScustomfieldsexist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');
        require_once($CFG->dirroot.'/local/eliscore/lib/data/customfield.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $contextlevel = $this->contextname_2_level($datarow['contextlevel']);
            $catobj = field_category::ensure_exists_for_contextlevel($datarow['category'], $contextlevel);
            $fieldrec = [
                'shortname' => $datarow['name'],
                'name' => $datarow['name'],
                'datatype' => $datarow['datatype'],
                'description' => '',
                'categoryid' => $catobj->id,
                'sortorder' => 0,
                'multivalued' => $datarow['multi'],
                'forceunique' => 0,
                // 'params' => 'a:0:{}',
            ];
            $fieldobj = field::ensure_field_exists_for_context_level(new field($fieldrec), $contextlevel, $catobj);

            $ownerrec = [
                'required' => 0,
                'edit_capability' => '',
                'view_capability' => '',
                'control' => $datarow['control'],
                'options_source' => '',
                'options' => str_replace(',', "\n", $datarow['options']),
                'columns' => 30,
                'rows' => 10,
                'maxlength' => 2048,
                'startyear' => 1970,
                'stopyear' => 2038,
                'inctime' => '0',
            ];
            field_owner::ensure_field_owner_exists($fieldobj, 'manual', $ownerrec);

            // Insert a default value for the field:
            if (!empty($datarow['default'])) {
                field_data::set_for_context_and_field(null, $fieldobj, empty($datarow['multi']) ? $datarow['default'] : [$datarow['default']]);
            }
        }
    }

    /**
     * @Given /^the following ELIS programs exist:$/
     */
    public function theFollowingElisProgramsExist(TableNode $table) {
        global $CFG;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/curriculum.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $pgm = new curriculum();
            $pgm->idnumber = $datarow['idnumber'];
            $pgm->name = $datarow['name'];
            $pgm->description = empty($datarow['description']) ? 'Description of the Program' : $datarow['description'];
            $pgm->reqcredits = $datarow['reqcredits'];
            $pgm->save();
        }
    }

    /**
     * @Given /^the following ELIS tracks exist:$/
     */
    public function theFollowingElisTracksExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/track.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $trk = new track();
            $trk->curid = $DB->get_field(curriculum::TABLE, 'id', ['idnumber' => $datarow['program_idnumber']]);
            $trk->idnumber = $datarow['idnumber'];
            $trk->name = $datarow['name'];
            $trk->description = empty($datarow['description']) ? 'Description of the Track' : $datarow['description'];
            $trk->save();
        }
    }

    /**
     * @Given /^the following ELIS courses exist:$/
     */
    public function theFollowingElisCoursesExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/course.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $crs = new course();
            $crs->idnumber = $datarow['idnumber'];
            $crs->name = $datarow['name'];
            $crs->credits = $datarow['credits'];
            $crs->completion_grade = $datarow['completion_grade'];
            $crs->syllabus = empty($datarow['syllabus']) ? 'Description of the Course' : $datarow['syllabus'];
            $crs->save();
        }
    }

    /**
     * @Given /^the following ELIS classes exist:$/
     */
    public function theFollowingElisClassesExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/pmclass.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $cls = new pmclass();
            $cls->idnumber = $datarow['idnumber'];
            $cls->courseid = $DB->get_field(course::TABLE, 'id', ['idnumber' => $datarow['course_idnumber']]);
            if (!empty($datarow['moodlecourse'])) {
                $cls->moodlecourseid = $DB->get_field('course', 'id', ['shortname' => $datarow['moodlecourse']]);
            }
            $cls->save();
        }
    }

    /**
     * @Given /^the following ELIS usersets exist:$/
     */
    public function theFollowingElisUsersetsExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/userset.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $us = new userset();
            $us->name = $datarow['name'];
            $us->display = $datarow['name'];
            $us->parent = ($datarow['parent_name'] == 'top') ? 0 : $DB->get_field(userset::TABLE, 'id', ['name' => $datarow['parent_name']]);
            $us->save();
        }
    }

    /**
     * @Given /^the following ELIS program enrolments exist:$/
     */
    public function theFollowingElisProgramEnrolmentsExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/curriculumstudent.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $cs = new curriculumstudent();
            $cs->userid = $DB->get_field(user::TABLE, 'id', ['idnumber' => $datarow['user_idnumber']]);
            $cs->curriculumid = $DB->get_field(curriculum::TABLE, 'id', ['idnumber' => $datarow['program_idnumber']]);
            if (!empty($datarow['completed'])) {
                $cs->completed = $datarow['completed'];
                $cs->timecompleted = empty($datarow['timecompleted']) ? time() : strtotime($datarow['timecompleted']);
                $cs->certificatecode = empty($datarow['certificatecode']) ?
                        substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 15)), 0, 15) : $datarow['certificatecode'];
            }
            $cs->save();
        }
    }

    /**
     * @Given /^the following ELIS track enrolments exist:$/
     */
    public function theFollowingElisTrackEnrolmentsExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/usertrack.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $ut = new usertrack();
            $ut->userid = $DB->get_field(user::TABLE, 'id', ['idnumber' => $datarow['user_idnumber']]);
            $ut->trackid = $DB->get_field(track::TABLE, 'id', ['idnumber' => $datarow['track_idnumber']]);
            $ut->save();
        }
    }

    /**
     * @Given /^the following ELIS class enrolments exist:$/
     */
    public function theFollowingElisClassEnrolmentsExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/student.class.php');
        static $statusmap = ['notcompleted' => 0, 'failed' => 1, 'passed' => 2];
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $ce = new student();
            $ce->userid = $DB->get_field(user::TABLE, 'id', ['idnumber' => $datarow['user_idnumber']]);
            $ce->classid = $DB->get_field(pmclass::TABLE, 'id', ['idnumber' => $datarow['class_idnumber']]);
            $completestatusid = $statusmap[$datarow['completestatus']];
            $ce->completestatusid = $completestatusid;
            if ($completestatusid) {
                $ce->completetime = time();
            }
            $ce->enrolmenttime = strtotime('-1 day');
            $ce->grade = $datarow['grade'];
            $ce->credits = $datarow['credits'];
            $ce->locked = $datarow['locked'];
            $ce->save();
        }
    }

    /**
     * @Given /^the following ELIS userset enrolments exist:$/
     */
    public function theFollowingElisUsersetEnrolmentsExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/clusterassignment.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $ca = new clusterassignment();
            $ca->userid = $DB->get_field(user::TABLE, 'id', ['idnumber' => $datarow['user_idnumber']]);
            $ca->clusterid = $DB->get_field(userset::TABLE, 'id', ['name' => $datarow['userset_name']]);
            $ca->plugin = $datarow['plugin'];;
            $ca->save();
        }
    }

    /**
     * @Given /^the following ELIS program course assignments exist:$/
     */
    public function theFollowingElisProgramCourseAssignmentsExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/curriculumcourse.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $cc = new curriculumcourse();
            $cc->courseid = $DB->get_field(course::TABLE, 'id', ['idnumber' => $datarow['course_idnumber']]);
            if (empty($cc->courseid)) {
                throw new \Exception('ELIS Course Description idnumber = "'.$datarow['course_idnumber'].'" does not exist!');
            }
            $cc->curriculumid = $DB->get_field(curriculum::TABLE, 'id', ['idnumber' => $datarow['program_idnumber']]);
            if (empty($cc->curriculumid)) {
                throw new \Exception('ELIS Program idnumber = "'.$datarow['program_idnumber'].'" does not exist!');
            }
            $cc->required = !empty($datarow['required']);
            $cc->save();
        }
    }
}
