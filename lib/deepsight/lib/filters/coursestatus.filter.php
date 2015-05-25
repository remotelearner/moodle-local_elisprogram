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
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Onwards Remote Learner.net Inc http://www.remote-learner.net
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

/**
 * A switch filter to change whether enrolled courses, not-enrolled courses ... are displayed.
 */
class deepsight_filter_coursestatus extends deepsight_filter_menuofchoices {
    /** @var array $choices array of filter options */
    protected $choices = array(
        'completed' => '',
        'notcompleted' => '',
        'enrolled' => '',
        'notenrolled' => ''
    );

    /** @var string $default choice */
    protected $default = '';

    /** @var int $userid */
    protected $userid = 0;

    /**
     * Constructor.
     *
     * @param moodle_database &$DB       The global moodle_database object.
     * @param string          $name      The name of the filter. Used when receiving data to determine where to send the data.
     * @param string          $label     The label that will be displayed on the filter button.
     * @param array           $fielddata An array of field information used by the filter. Formatted like [field]=>[label].
     *                                   Usually this is what field the filter will use to affect the datatable results, but refer
     *                                   to the individual filter for specifics.
     * @param string          $endpoint  The endpoint to make requests to, when searching for a choice.
     */
    public function __construct(moodle_database &$DB, $name, $label, array $fielddata = array(), $endpoint=null) {
        if (isset($fielddata['userid'])) {
            $this->userid = $fielddata['userid'];
            unset($fielddata['userid']);
        }
        parent::__construct($DB, $name, $label, $fielddata, $endpoint);
        $this->postconstruct();
    }

    /**
     * Sets the available choices - not enrolled, enrolled, or all.
     */
    public function postconstruct() {
        $this->choices['notenrolled'] = get_string('ds_notenrolled', 'local_elisprogram');
        $this->choices['enrolled'] = get_string('ds_enrolled', 'local_elisprogram');
        $this->choices['notcompleted'] = get_string('ds_notcompleted', 'local_elisprogram');
        $this->choices['completed'] = get_string('ds_completed', 'local_elisprogram');
    }

    /**
     * Set the default choice.
     *
     * @param string $default The default choice (corresponds to an index of $this->choices).
     */
    public function set_default($default) {
        $this->default = $default;
    }

    /**
     * Set the user ID we're using to filter.
     *
     * @param int $userid The user ID to set.
     */
    public function set_userid($userid) {
        if (is_int($userid)) {
            $this->userid = $userid;
        }
    }

    /**
     * Get the set user ID.
     *
     * @return int The current user ID.
     */
    public function get_userid() {
        return $this->userid;
    }

    /**
     * Get SQL to show only users that fit into the currently selected option.
     *
     * Will force an enrolment to be present, force an enrolment to not be preset, or return empty SQL.
     *
     * @param mixed $data The data from the filter send from the javascript.
     * @return array An array consisting of filter sql as index 0, and an array of parameters as index 1
     */
    public function get_filter_sql($data) {
        if (empty($this->userid)) {
            throw new Exception('No userid set for coursestatus filter.');
        }
        $data = (!empty($data) && is_array($data)) ? $data : explode(',', $this->default);
        $sql = array();
        $params = array();
        foreach ($data as $option) {
            if ($option == 'notenrolled') {
                $sql[] = 'enrol.id IS NULL';
            } else if ($option == 'enrolled') {
                $sql[] = 'enrol.id IS NOT NULL';
            } else if ($option == 'notcompleted') {
                $sql[] = '(enrol.id IS NOT NULL AND enrol.completestatusid = '.STUSTATUS_NOTCOMPLETE.')';
            } else if ($option == 'completed') {
                $sql[] = '(enrol.id IS NOT NULL AND enrol.completestatusid > '.STUSTATUS_NOTCOMPLETE.')';
            }
        }
        if (empty($sql)) {
            return array('', array());
        }
        $allsql = '('.implode(' OR ', $sql).')';
        return array($allsql, $params);
    }
}
