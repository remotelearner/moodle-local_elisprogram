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
 * @copyright  (C) 2014 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

/**
 * Action to assign courseset to a course.
 */
abstract class deepsight_action_coursecrsset_assignedit extends deepsight_action_confirm {
    /**
     * @var string The label for the action.
     */
    public $label = 'Assign';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-assoc';

    /**
     * @var string Mode indicating to the javascript how to operate.
     */
    public $mode = 'assign';

    /**
     * Provide options to the javascript.
     * @return array An array of options.
     */
    public function get_js_opts() {
        global $CFG;
        $opts = parent::get_js_opts();
        $opts['condition'] = $this->condition;
        $opts['opts']['actionurl'] = $this->endpoint;
        $opts['opts']['mode'] = $this->mode;
        $opts['opts']['langbulkconfirm'] = get_string('ds_bulk_confirm', 'local_elisprogram');
        $opts['opts']['langbulkconfirmactive'] = get_string('ds_bulk_confirm_crsset_active', 'local_elisprogram');
        $opts['opts']['langconfirmactive'] = get_string('confirm_delete_active_courseset_course', 'local_elisprogram');
        $opts['opts']['langworking'] = get_string('ds_working', 'local_elisprogram');
        $opts['opts']['langyes'] = get_string('yes', 'moodle');
        $opts['opts']['langno'] = get_string('no', 'moodle');
        $opts['opts']['langchanges'] = get_string('ds_changes', 'local_elisprogram');
        $opts['opts']['langnochanges'] = get_string('ds_nochanges', 'local_elisprogram');
        $opts['opts']['langgeneralerror'] = get_string('ds_unknown_error', 'local_elisprogram');
        $opts['opts']['langtitle'] = get_string('ds_assocdata', 'local_elisprogram');
        return $opts;
    }

    /**
     * Process association data from the form.
     * @param string $assocdata JSON-formatted association data.
     * @param string $bulkaction Whether this is a bulk action or not.
     * @return array The formatted and cleaned association data.
     */
    protected function process_incoming_assoc_data($assocdata, $bulkaction) {
        return array(); // no association data
    }

    /**
     * Formats association data for display in the table post-edit.
     * @param array $assocdata The incoming association data
     * @return array The formatted association data.
     */
    protected function format_assocdata_for_display($assocdata) {
        return $assocdata;
    }

    /**
     * Determine whether the current user can manage the course - crsset association.
     * @param int $crssetid The ID of the courseset.
     * @param int $courseid The ID of the course.
     * @return bool Whether the current can manage (true) or not (false)
     */
    protected function can_manage_assoc($crssetid, $courseid) {
        global $USER;
        $perm = 'local/elisprogram:associate';
        $crssetassocctx = pm_context_set::for_user_with_capability('courseset', $perm, $USER->id);
        $crssetassociateallowed = ($crssetassocctx->context_allowed($crssetid, 'courseset') === true) ? true : false;
        $courseassocctx = pm_context_set::for_user_with_capability('course', $perm, $USER->id);
        $courseassociateallowed = ($courseassocctx->context_allowed($courseid, 'course') === true) ? true : false;
        return ($crssetassociateallowed === true && $courseassociateallowed === true) ? true : false;
    }
}

/**
 * Action to assign courseset to a course.
 */
class deepsight_action_coursecrsset_assign extends deepsight_action_coursecrsset_assignedit {
    /**
     * @var string The label for the action.
     */
    public $label = 'Assign';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-assoc';

    /**
     * @var string Mode indicating to the javascript how to operate.
     */
    public $mode = 'assign';

    /**
     * Constructor.
     * @param moodle_database $DB The active database connection.
     * @param string $name The unique name of the action to use.
     */
    public function __construct(moodle_database &$DB, $name) {
        parent::__construct($DB, $name);
        $this->label = ucwords(get_string('assign', 'local_elisprogram'));
    }

    /**
     * Edit courseset- course associations.
     * @param array $elements An array of course information to edit.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $courseid = required_param('id', PARAM_INT);
        // No association data
        foreach ($elements as $crssetid => $label) {
            if ($this->can_manage_assoc($crssetid, $courseid) === true) {
                $crssetcourse = new crssetcourse(array('crssetid' => $crssetid, 'courseid' => $courseid));
                $crssetcourse->save();
            }
        }
        $formatteddata = $this->format_assocdata_for_display(array());
        return array(
            'result' => 'success',
            'msg' => 'Success',
            'displaydata' => $formatteddata,
            'saveddata' => array()
        );
    }
}

/**
 * Edit the course - courseset assignment.
 */
class deepsight_action_coursecrsset_edit extends deepsight_action_coursecrsset_assignedit {
    /**
     * @var string The label for the action.
     */
    public $label = 'Edit';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-edit';

    /**
     * @var string Mode indicating to the javascript how to operate.
     */
    public $mode = 'edit';

    /**
     * Constructor.
     * @param moodle_database $DB The active database connection.
     * @param string $name The unique name of the action to use.
     */
    public function __construct(moodle_database &$DB, $name) {
        parent::__construct($DB, $name);
        $this->label = ucwords(get_string('edit', 'local_elisprogram'));
    }

    /**
     * Edit course - courseset associations.
     * @param array $elements An array of course information to edit.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $courseid = required_param('id', PARAM_INT);
        // No association data
        foreach ($elements as $crssetid => $label) {
            if ($this->can_manage_assoc($crssetid, $courseid) === true) {
                $assoc = $DB->get_record(crssetcourse::TABLE, array('crssetid' => $crssetid, 'courseid' => $courseid));
                if (!empty($assoc)) {
                    $crssetcourse = new crssetcourse($assoc);
                    $crssetcourse->save();
                }
            }
        }
        $formatteddata = $this->format_assocdata_for_display($assocdata);
        return array(
            'result' => 'success',
            'msg' => 'Success',
            'displaydata' => $formatteddata,
            'saveddata' => $assocdata
        );
    }
}

/**
 * An action to unassign courseset from a course.
 */
class deepsight_action_coursecrsset_unassign extends deepsight_action_standard {
    /**
     * The javascript class to use.
     */
    const TYPE = 'coursecrsset';

    /**
     * @var string The label for the action.
     */
    public $label = 'Unassign';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-unassoc';

    /**
     * @var string Mode indicating to the javascript how to operate.
     */
    public $mode = 'remove'; // TBD

    /**
     * Constructor.
     * @param moodle_database $DB The active database connection.
     * @param string $name The unique name of the action to use.
     * @param string $descsingle The description when the confirmation is for a single element.
     *        TBD: coursesets require different confirm msg when active
     * @param string $descmultiple The description when the confirmation is for the bulk list.
     */
    public function __construct(moodle_database &$DB, $name, $descsingle='', $descmultiple='') {
        parent::__construct($DB, $name);
        $this->label = ucwords(get_string('unassign', 'local_elisprogram'));

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('course', 'local_elisprogram'));
        $langelements->actionelement = strtolower(get_string('courseset', 'local_elisprogram'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_unassign_confirm', 'local_elisprogram', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('course', 'local_elisprogram'));
        $langelements->actionelement = strtolower(get_string('coursesets', 'local_elisprogram'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_unassign_confirm_multi', 'local_elisprogram', $langelements);
    }

    /**
     * Provide options to the javascript.
     * @return array An array of options.
     */
    public function get_js_opts() {
        global $CFG;
        $opts = parent::get_js_opts();
        $opts['condition'] = $this->condition;
        $opts['opts']['actionurl'] = $this->endpoint;
        $opts['opts']['mode'] = $this->mode;
        $opts['opts']['langbulkconfirm'] = get_string('ds_bulk_confirm', 'local_elisprogram');
        $opts['opts']['langbulkconfirmactive'] = get_string('ds_bulk_confirm_crsset_active', 'local_elisprogram');
        $opts['opts']['langconfirmactive'] = get_string('confirm_delete_active_courseset_course', 'local_elisprogram');
        $opts['opts']['langworking'] = get_string('ds_working', 'local_elisprogram');
        $opts['opts']['langyes'] = get_string('yes', 'moodle');
        $opts['opts']['langno'] = get_string('no', 'moodle');
        $opts['opts']['langchanges'] = get_string('ds_changes', 'local_elisprogram');
        $opts['opts']['langnochanges'] = get_string('ds_nochanges', 'local_elisprogram');
        $opts['opts']['langgeneralerror'] = get_string('ds_unknown_error', 'local_elisprogram');
        $opts['opts']['langtitle'] = get_string('ds_assocdata', 'local_elisprogram');
        return $opts;
    }

    /**
     * Unassign the coursesets from the course.
     * @param array $elements An array of courseset information to unassign from the course.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $courseid = required_param('id', PARAM_INT);
        foreach ($elements as $crssetid => $label) {
            if ($this->can_unassign($courseid, $crssetid) === true) {
                $assignrec = $DB->get_record(crssetcourse::TABLE, array('crssetid' => $crssetid, 'courseid' => $courseid));
                $crssetcourse = new crssetcourse($assignrec);
                $crssetcourse->delete();
            }
        }
        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can unassign the courseset from the course.
     * @param int $courseid The ID of the course.
     * @param int $crssetid The ID of the courseset.
     * @return bool Whether the current can unassign (true) or not (false)
     */
    protected function can_unassign($courseid, $crssetid) {
        global $USER;

        $crssetctx = \local_elisprogram\context\courseset::instance($crssetid);
        $candelactive = has_capability('local/elisprogram:courseset_delete_active', $crssetctx);
        if (!$candelactive) {
            $crsset = new courseset($crssetid);
            foreach ($crsset->programs as $programcrsset) {
                if ($programcrsset->is_active($courseid)) {
                    return false;
                }
            }
        }

        $perm = 'local/elisprogram:associate';
        $crssetassocctx = pm_context_set::for_user_with_capability('courseset', $perm, $USER->id);
        $crssetassociateallowed = ($crssetassocctx->context_allowed($crssetid, 'courseset') === true) ? true : false;
        $courseassocctx = pm_context_set::for_user_with_capability('course', $perm, $USER->id);
        $courseassociateallowed = ($courseassocctx->context_allowed($courseid, 'course') === true) ? true : false;
        return ($crssetassociateallowed === true && $courseassociateallowed === true) ? true : false;
    }
}
