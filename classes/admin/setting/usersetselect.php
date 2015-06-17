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

namespace local_elisprogram\admin\setting;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../../../../lib/adminlib.php');
require_once(__DIR__.'/../../../../../lib/outputcomponents.php');
require_once(__DIR__.'/../../../lib/filtering/clustertree.php');
require_once(__DIR__.'/../../../form/configusersetselectform.class.php');

/**
 * Config-setting class for cluster/userset tree selection.
 */
class usersetselect extends \admin_setting_configtext {
    /** @var object $filter the clustertree filter object. */
    protected $filter;
    /** @var object $form the form object. */
    protected $form;
    /** @var string $instanceid the instance id. */
    protected $instanceid = 'eliswidget_trackenrol_usersettree';

    /**
     * Constructor
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param array $defaultsetting array of selected items
     * @param array $options array of settings.
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, $options) {
        $this->filter = static::init_filter('cluster', 'cluster', $this->instanceid);
        parent::__construct($name, $visiblename, $description, $defaultsetting, $options);
    }

    /**
     * Initialize clustertree filter for admin settings.
     * @param string $alias the table alias of the filter.
     * @param string $name the name of the filter instance.
     * @param string $instanceid the filter's instance id, 'eliswidget_trackenrol_usersettree'.
     * @return object the clustertree filter object.
     */
    public static function init_filter($alias = 'cluster', $name = 'cluster', $instanceid = 'eliswidget_trackenrol_usersettree') {
        $enabletreelabel = get_string('enable_tree', 'local_elisprogram');
        $enabledropdownlabel = get_string('enable_dropdown', 'local_elisprogram');
        $helplabel = get_string('adminsetting_usersettree_help', 'local_elisprogram');
        $clustertreehelp = array($instanceid, $helplabel, 'local_elisprogram');
        $clustertreeoptions = array(
            'fieldset'             => 0,
            'dropdown_button_text' => $enabletreelabel,
            'tree_button_text'     => $enabledropdownlabel,
            'report_id'            => $instanceid,
            'report_shortname'     => $instanceid,
            'help'                 => $clustertreehelp);
        $clusterheading = get_string('adminsetting_usersettree_description', 'local_elisprogram');
        return new \generalized_filter_clustertree('cluster', $alias, $name, $clusterheading, false, 'clustertree', $clustertreeoptions);
    }

    /**
     * Is setting related to query text - used when searching
     * @param string $query
     * @return bool
     */
    public function is_related($query) {
        if (strpos(\core_text::strtolower($this->name), $query) !== false) {
            return true;
        }
        if (strpos(\core_text::strtolower($this->visiblename), $query) !== false) {
            return true;
        }
        if (strpos(\core_text::strtolower($this->description), $query) !== false) {
            return true;
        }
        return false;
    }

    /**
     * Returns userset-select tree HTML
     *
     * @todo Add vartype handling to ensure $data is an array
     * @param array $data Array of values to select by default
     * @param string $query
     * @return string userset-select tree.
     */
    public function output_html($data, $query = '') {
        $this->form = new \configusersetselectform(null, array(
            'id' => $this->instanceid,
            $this->get_full_name() => '1' // required dummy.
        ));
        $mform = $this->form->get_form();
        $this->filter->setupForm($mform);
        $this->form->set_data($this->get_setting());
        $return = \html_writer::tag('div', $this->form->render(), array('id' => "php_report_body_{$this->instanceid}"));
        $return = preg_replace('/<form.*>/', '', $return, 1); // Cannot use html_writer for RegEx.
        $return = str_replace(\html_writer::end_tag('form'), '', $return);
        return format_admin_setting($this, $this->visiblename, $return, $this->description, true, '', get_string('allowall', 'local_elisprogram'), $query);
    }

    /**
     * Return the setting
     *
     * @return mixed returns config if successful else null
     */
    public function get_setting() {
        try {
            $data = @unserialize($this->config_read($this->name));
        } catch (Exception $e) {
            $data = array();
        }
        return $data;
    }

    /**
     * Store new setting
     *
     * @param mixed $data string or array, must not be NULL
     * @return string empty string if ok, string error message otherwise
     */
    public function write_setting($data) {
        $data = $_POST;
        foreach ($data as $key => $val) {
            if (strpos($key, 'cluster') !== 0) {
                unset($data[$key]);
            }
        }
        if (!empty($data)) {
            $this->config_write($this->name, serialize($data));
            return '';
        }
        return get_string('error');
    }

    /**
     * Execute postupdatecallback if necessary.
     * @param mixed $original original value before write_setting()
     * @return bool true if changed, false if not.
     */
    public function post_write_settings($original) {
        return true;
    }
}
