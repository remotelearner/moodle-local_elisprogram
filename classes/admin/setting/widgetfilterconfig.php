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
require_once(__DIR__.'/../../../form/configusersetselectform.class.php');

/**
 * Config-setting class for widget filter configurations.
 */
class widgetfilterconfig extends \admin_setting_configtext {
    /** @var string datatype */
    protected $datatype = 'text';

    /** @var int default for radio buttons */
    protected $defaultradio = 0;

    /** @var array menu options/choices */
    protected $options = [];

    /**
     * Constructor
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised.
     * @param string $description long localised info.
     * @param string $datatype the data type/html control.
     * @param int $default the radio default integer (0=>Visible, 1=>Hidden).
     * @param array $options array of settings.
     */
    public function __construct($name, $visiblename, $description, $datatype = '', $default = 0, $options = []) {
        if (!empty($datatype)) {
            $this->datatype = $datatype;
        }
        $this->defaultradio = $default;
        $this->options = $options;
        parent::__construct($name, $visiblename, $description, '', $options);
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
     * Returns widget field filter config form/line.
     *
     * @todo Add vartype handling to ensure $data is an array
     * @param array $data Array of values to select by default
     * @param string $query
     * @return string the settings 'form'.
     */
    public function output_html($data, $query = '') {
        global $PAGE;
        require_once(__DIR__.'/../../../form/configusersetselectform.class.php');
        $radiolabels = [
                get_string('visible'),
                get_string('hide'),
                get_string('default'),
                get_string('student_lock', 'local_elisprogram') // TBD?
        ];
        $setting = $this->get_setting();
        $return = \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $this->get_full_name(), 'value' => '1']);
        $radoptions = ['name' => $this->name.'_radio', 'type' => 'radio'];
        $disabled = false;
        for ($i = 0; $i < 4; ++$i) {
            unset($radoptions['checked']);
            $radoptions['value'] = $i;
            if (isset($setting[$this->name.'_radio'])) {
                if ($setting[$this->name.'_radio'] == $i) {
                    $radoptions['checked'] = 'checked';
                    $disabled = $i < 2;
                }
            } else if ($this->defaultradio == $i) {
                $radoptions['checked'] = 'checked';
                $disabled = $i < 2;
            }
            $return .= \html_writer::tag('input', $radiolabels[$i], $radoptions).'&nbsp;&nbsp;&nbsp;';
        }
        $defaultname = $this->name.'_default';
        $forceline = false;
        $attrs = [];
        if ($disabled) {
           $attrs['disabled'] = 'true';
        }
        switch ($this->datatype) {
            case 'text':
            case 'textarea':
                $return .= get_string('filter_value', 'local_elisprogram').\html_writer::empty_tag('input', array_merge(['name' => $defaultname,
                    'type' => 'text', 'value' => !empty($setting[$defaultname]) ? $setting[$defaultname] : ''], $attrs));
                break;
            case 'bool':
                $return .= get_string('filter_value', 'local_elisprogram').\html_writer::empty_tag('input', array_merge(['name' => $defaultname,
                    'type' => 'checkbox', 'value' => get_string('yes'), 'checked' => !empty($setting[$defaultname]) ? 'checked' : 'false'], $attrs));
                break;
            case 'menu':
                $return .= get_string('filter_value', 'local_elisprogram').\html_writer::select($this->options, $defaultname.'[]',
                        isset($setting[$defaultname]) ? $setting[$defaultname] : '', '', array_merge(['multiple' => 'multiple'], $attrs));
                break;
            case 'date':
            case 'datetime':
                $forceline = true;
                $form = new \configusersetselectform(null, [/* TBD */]);
                $mform = $form->get_form();
                $mform->addElement(($this->datatype == 'date') ? 'date_selector' : 'date_time_selector', $defaultname, get_string('filter_value', 'local_elisprogram'),
                        [], $attrs);
                $form->set_data($setting);
                $formselector = $form->render();
                $formselector = preg_replace('/<form.*>/', '', $formselector, 1); // Cannot use html_writer for RegEx.
                $formselector = str_replace(\html_writer::end_tag('form'), '', $formselector);
                $return .= $formselector;
                break;
            case 'time':
                $forceline = true;
                $form = new \configusersetselectform(null, [/* TBD */]);
                $mform = $form->get_form();
                $mform->addElement('time_selector', $defaultname, get_string('filter_value', 'local_elisprogram'), [], $attrs);
                $form->set_data($setting);
                $formselector = $form->render();
                $formselector = preg_replace('/<form.*>/', '', $formselector, 1); // Cannot use html_writer for RegEx.
                $formselector = str_replace(\html_writer::end_tag('form'), '', $formselector);
                $return .= $formselector;
                break;
        }
        $return .= '&nbsp;&nbsp;&nbsp;';
        $js = "YUI().use('event', 'node', function(Y) {
                Y.on('click', function(e) {
                    Y.all('*[name^=\"{$defaultname}\"]').set('disabled', e.target.getAttribute('value') < 2);
                }, 'input[name=\"{$this->name}_radio\"]');";
        if ($forceline) {
            // Attempt to force all form elements to single line.
            $js .= "Y.one('#fitem_id_{$defaultname}').ancestor('fieldset').setStyle('display', 'inline-block');";
        }
        $js .= "});";
        $return .= \html_writer::script($js);
        return format_admin_setting($this, $this->visiblename, $return, $this->description, true, '',
                $this->defaultradio ? get_string('hide') : get_string('visible'), $query);
    }

    /**
     * Return the setting
     *
     * @return mixed returns config if successful else null
     */
    public function get_setting() {
        $data = [$this->name.'_radio' => $this->config_read($this->name.'_radio')];
        $config = get_config($this->plugin);
        foreach ($config as $key => $val) {
            if (strpos($key, $this->name.'_default') === 0) {
                $data[$key] = (strpos($val, 'a:') === 0) ? @unserialize($val) : $val;
            }
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
        if (isset($_POST[$this->name.'_radio'])) {
            $this->config_write($this->name.'_radio', $_POST[$this->name.'_radio']);
            foreach ($_POST as $key => $val) {
                if (strpos($key, $this->name.'_default') === 0) {
                    $this->config_write($key, is_array($val) ? serialize($val) : $val);
                }
            }
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
