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

    /** @var string $tablealias */
    protected $tablealias = 'enrol';

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
        if (isset($fielddata['tablealias'])) {
            $this->tablealias = $fielddata['tablealias'];
            unset($fielddata['tablealias']);
        }
        foreach ($fielddata as $key => $val) {
            if (strrpos($key, 'userid') == (strlen($key) - strlen('userid'))) {
                $this->userid = $fielddata[$key];
                unset($fielddata[$key]);
                break;
            }
        }
        parent::__construct($DB, $name, $label, $fielddata, $endpoint);
        $this->postconstruct();
    }

    /**
     * Get the static choices for this filter.
     *
     * @return array The array of choices.
     */
    public static function get_static_choices() {
        return [
            'notenrolled' => get_string('ds_notenrolled', 'local_elisprogram'),
            'enrolled' => get_string('ds_enrolled', 'local_elisprogram'),
            'notcompleted' => get_string('ds_notcompleted', 'local_elisprogram'),
            'completed' => get_string('ds_completed', 'local_elisprogram'),
        ];
    }

    /**
     * Sets the available choices - not enrolled, enrolled, or all.
     */
    public function postconstruct() {
        $this->choices = static::get_static_choices();
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
                $sql[] = "({$this->tablealias}.id IS NULL
                           AND NOT EXISTS (SELECT 'x'
                                             FROM {local_elisprogram_cls} clscs
                                             JOIN {local_elisprogram_cls_enrol} enrolcs ON enrolcs.classid = clscs.id
                                                  AND enrolcs.userid = ?
                                            WHERE clscs.courseid = element.id))";
                $params[] = $this->userid;
            } else if ($option == 'enrolled') {
                $sql[] = "{$this->tablealias}.id IS NOT NULL";
            } else if ($option == 'notcompleted') {
                $sql[] = "({$this->tablealias}.id IS NOT NULL AND {$this->tablealias}.completestatusid = ".STUSTATUS_NOTCOMPLETE.')';
            } else if ($option == 'completed') {
                $sql[] = "({$this->tablealias}.id IS NOT NULL AND {$this->tablealias}.completestatusid > ".STUSTATUS_NOTCOMPLETE.')';
            }
        }
        if (empty($sql)) {
            return array('', array());
        }
        $allsql = '('.implode(' OR ', $sql).')';
        return array($allsql, $params);
    }

    /**
     * Returns options for the javascript object.
     *
     * @return array An array of options.
     */
    public function get_js_opts() {
        $opts = parent::get_js_opts();
        $opts['no_search'] = true;
        return $opts;
    }
}
