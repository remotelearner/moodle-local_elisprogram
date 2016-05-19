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
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2013 Onwards Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

/**
 * Trait containing shared methods.
 */
trait deepsight_action_usertrack {
    /**
     * Determine whether the current user can manage an association.
     *
     * @param int $userid The ID of the main element. The is the ID of the 'one', in a 'many-to-one' association.
     * @param int $trackid The ID of the incoming element. The is the ID of the 'many', in a 'many-to-one' association.
     * @return bool Whether the current can manage (true) or not (false)
     */
    protected function can_manage_assoc($userid, $trackid) {
        return usertrack::can_manage_assoc($userid, $trackid);
    }
}

/**
 * Trait containing common methods for usertrack and trackuser unassign actions.
 */
trait deepsight_action_usertrack_trackuser {
    /**
     * Attempt an unenrolment of usertrack or trackuser.
     *
     * @param int $mainelementid The ID of the main element. The is the ID of the 'one', in a 'many-to-one' association.
     * @param array $elements An array of items to associate to the main element.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @param string $assocclass The class used for this association.
     * @param string $assocparams Parameters used to check for/create the association. Must contain keys:
     *                                 'main' => The field name that corresponds to the main element's ID. Ex. 'courseid'
     *                                 'incoming' => The field name that corresponds to incoming elements' IDs. Ex. 'programid'
     *                                 'removefromprogram' => boolean flag to cascade unenrol track program.
     *                                 'removefromclasses' => boolean flag to cascade unenrol track classes.
     * @param string $faillang If there were elements that failed, use this string as an error message. If null, language string
     *                         ds_action_generic_bulkfail from local_elisprogram will be used.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function attempt_unenrol($mainelementid, $elements, $bulkaction, $assocclass, $assocparams, $faillang = null) {
        global $DB;
        $failedops = [];
        foreach ($elements as $incomingelementid => $label) {
            if ($this->can_manage_assoc($mainelementid, $incomingelementid) === true) {
                $assocqueryparams = [
                    $assocparams['main'] => $mainelementid,
                    $assocparams['incoming'] => $incomingelementid,
                ];
                $assignrec = $DB->get_record($assocclass::TABLE, $assocqueryparams);
                if (!empty($assignrec)) {
                    try {
                        $association = new $assocclass($assignrec);
                        $association->unenrol($assocparams['removefromprogram'], $assocparams['removefromclasses']);
                    } catch (\Exception $e) {
                        if ($bulkaction === true) {
                            $failedops[] = $incomingelementid;
                        } else {
                            throw $e;
                        }
                    }
                } else if (empty($assocparams['ignore_unassigned'])) {
                    $failedops[] = $incomingelementid;
                }
            } else {
                $failedops[] = $incomingelementid;
            }
        }

        if ($bulkaction === true && !empty($failedops)) {
            if ($faillang === null || !is_string($faillang)) {
                $faillang = get_string('ds_action_generic_bulkfail', 'local_elisprogram');
            }
            return [
                'result' => 'partialsuccess',
                'msg' => $faillang,
                'failedops' => $failedops,
            ];
        } else if (empty($failedops)) {
            return [
                'result' => 'success',
                'msg' => 'Success',
            ];
        } else {
            return [
                'result' => 'fail',
                'msg' => get_string('not_permitted', 'local_elisprogram')
            ];
        }
    }
}

/**
 * An action to assign tracks to a user.
 */
class deepsight_action_usertrack_assign extends deepsight_action_confirm {
    use deepsight_action_usertrack;

    public $label = 'Assign';
    public $icon = 'elisicon-assoc';

    /**
     * Constructor.
     * @param moodle_database $DB The active database connection.
     * @param string $name The unique name of the action to use.
     * @param string $descsingle The description when the confirmation is for a single element.
     * @param string $descmultiple The description when the confirmation is for the bulk list.
     */
    public function __construct(moodle_database &$DB, $name, $descsingle='', $descmultiple='') {
        parent::__construct($DB, $name);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('user', 'local_elisprogram'));
        $langelements->actionelement = strtolower(get_string('track', 'local_elisprogram'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_assign_confirm', 'local_elisprogram', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('user', 'local_elisprogram'));
        $langelements->actionelement = strtolower(get_string('tracks', 'local_elisprogram'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_assign_confirm_multi', 'local_elisprogram', $langelements);
    }

    /**
     * Assign the tracks to the user.
     * @param array $elements An array containing information on tracks to assign to the user.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $userid = required_param('id', PARAM_INT);
        $user = new user($userid);

        $failedops = [];
        foreach ($elements as $trackid => $label) {
            if ($this->can_manage_assoc($user->id, $trackid) === true) {
                try {
                    usertrack::enrol($user->id, $trackid);
                } catch (\Exception $e) {
                    if ($bulkaction === true) {
                        $failedops[] = $trackid;
                    } else {
                        throw $e;
                    }
                }
            } else {
                $failedops[] = $trackid;
            }
        }

        if ($bulkaction === true && !empty($failedops)) {
             return [
                'result' => 'partialsuccess',
                'msg' => get_string('ds_action_generic_bulkfail', 'local_elisprogram'),
                'failedops' => $failedops,
            ];
        } else if (empty($failedops)) {
            return [
                'result' => 'success',
                'msg' => 'Success'
            ];
        } else {
            return [
                'result' => 'fail',
                'msg' => get_string('not_permitted', 'local_elisprogram')
            ];
        }
    }
}

/**
 * An action to unassign tracks from a user.
 */
class deepsight_action_usertrack_unassign extends deepsight_action_standard {
    use deepsight_action_usertrack;
    use deepsight_action_usertrack_trackuser;

    /**
     * The javascript class to use.
     */
    const TYPE = 'usertrack';

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
     * @param string $descmultiple The description when the confirmation is for the bulk list.
     */
    public function __construct(moodle_database &$DB, $name, $descsingle='', $descmultiple='') {
        parent::__construct($DB, $name);
        $this->label = ucwords(get_string('unassign', 'local_elisprogram'));

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('user', 'local_elisprogram'));
        $langelements->actionelement = strtolower(get_string('track', 'local_elisprogram'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_unassign_confirm', 'local_elisprogram', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('user', 'local_elisprogram'));
        $langelements->actionelement = strtolower(get_string('tracks', 'local_elisprogram'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_unassign_confirm_multi', 'local_elisprogram', $langelements);
    }

    /**
     * Provide options to the javascript.
     * @return array An array of options.
     */
    public function get_js_opts() {
        $opts = parent::get_js_opts();
        $opts['condition'] = $this->condition;
        $opts['opts']['actionurl'] = $this->endpoint;
        $opts['opts']['desc_single'] = $this->descsingle;
        $opts['opts']['desc_multiple'] = $this->descmultiple;
        $opts['opts']['mode'] = 'delete'; // TBD
        $opts['opts']['lang_bulk_confirm'] = get_string('ds_bulk_confirm', 'local_elisprogram');
        $opts['opts']['lang_working'] = get_string('ds_working', 'local_elisprogram');
        $opts['opts']['langrmprg'] = get_string('usertrack_removefromprogram', 'local_elisprogram');
        $opts['opts']['langrmclasses'] = get_string('usertrack_removefromclasses', 'local_elisprogram');
        $opts['opts']['langwarngrades'] = get_string('usertrack_warngrades', 'local_elisprogram');
        $opts['opts']['langyes'] = get_string('yes', 'moodle');
        $opts['opts']['langno'] = get_string('no', 'moodle');
        return $opts;
    }

    /**
     * Unenrol user from tracks
     * @param array $elements An array of userset information to delete.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        $userid = required_param('id', PARAM_INT);
        $rmprg = optional_param('rmprg', 0, PARAM_INT);
        $rmclasses = optional_param('rmclasses', 0, PARAM_INT);
        $assocclass = 'usertrack';
        $assocparams = ['main' => 'userid', 'incoming' => 'trackid', 'removefromprogram' => $rmprg, 'removefromclasses' => $rmclasses];
        return $this->attempt_unenrol($userid, $elements, $bulkaction, $assocclass, $assocparams);
    }
}
