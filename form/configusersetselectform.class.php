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
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class configusersetselectform extends moodleform {
    /** @var array $uniqueids */
    protected $uniqueids = array(); // TBD?

    /**
     * Standard form definition
     */
    public function definition() {
        $mform =& $this->_form;
        if (isset($this->_customdata) && is_array($this->_customdata)) {
            foreach ($this->_customdata as $key => $val) {
                $mform->addElement('hidden', $key);
                $mform->setType($key, is_string($val) ? PARAM_TEXT : PARAM_INT); // TBD?
                $mform->setDefault($key, $val);
            }
        }
    }

    /**
     * Expose underlying form object.
     * @return object the underlying form object.
     */
    public function get_form() {
        return $this->_form;
    }
}
