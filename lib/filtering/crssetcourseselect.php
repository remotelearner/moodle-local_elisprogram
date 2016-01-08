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
 * @copyright  (C) 2008-2016 Remote Learner.net Inc http://www.remote-learner.net
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/local/eliscore/lib/filtering/dependentselect.php');

/**
 * CourseSet-Course dependent select filter.
 */
class generalized_filter_crssetcourseselect extends generalized_filter_dependentselect {
    /** @var array options for the list values */
    var $_options;

    /** @var string field spec. */
    var $_field;

    /**
     * Constructor
     * @param string $uniqueid A unique identifier for the filter.
     * @param string $alias Alias for the table being filtered on.
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    public function __construct($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        global $USER;

        $options['numeric'] = true;
        $options['default'] = '0';

        $choicesarray = array('0' => get_string('selectacourse', 'local_elisprogram'));

        $contexts = get_contexts_by_capability_for_user('course', 'local/elisreports:view', $USER->id);
        $records = course_get_listing('crs.idnumber', 'ASC', 0, 0, '', '', $contexts); // TBD.
        foreach ($records as $record) {
            $choicesarray[$record->id] = $record->idnumber;
        }
        unset($records);

        $options['choices'] = $choicesarray;
        parent::__construct($uniqueid, $alias, $name, $label, $advanced, $field, $options);
    }

    /**
     * Override this method to return the main pulldown option
     * @return array List of options keyed on id
     */
    public function get_main_options() {
        global $USER;

        $crssetsarray = array('0' => get_string('selectacrsset', 'local_elisprogram'));
        // Fetch array of allowed coursesets.
        $contexts = get_contexts_by_capability_for_user('courseset', 'local/elisreports:view', $USER->id); // TBD.
        $records = courseset_get_listing('name', 'ASC', 0, 0, '', '', $contexts);
        if ($records && $records->valid()) {
            $allowedcrssets = array();
            foreach ($records as $key => $record) {
                $allowedcrssets[$key] = (strlen($record->name) > 80) ? substr($record->name, 0, 80).'...' : $record->name;
            }
            sort($allowedcrssets);
            foreach ($allowedcrssets as $key => $allowedcrsset) {
                $crssetsarray = array($key, $allowedcrsset);
            }
            unset($allowedcrssets);
        }
        unset($records);
        return $crssetsarray;
    }
}
