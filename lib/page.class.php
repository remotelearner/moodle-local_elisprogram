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
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elis::lib('page.class.php');

abstract class pm_page extends elis_page {
    /**
     * The page's short name
     */
    var $pagename;

    /**
     * Constructor.
     *
     * @param array $params array of URL parameters.  If  $params is not
     * specified, the constructor for each subclass should load the parameters
     * from the current HTTP request.
     */
    public function __construct(array $params = null) {
        // Load any component elistabs defined by plugins.
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/ddllib.php');
        $table = new xmldb_table('local_elisprogram_tab_defs');
        if ($DB->get_manager()->table_exists($table)) {
            $externaltabdefs = $DB->get_recordset('local_elisprogram_tab_defs', array('parent' => get_class($this)));
            if ($externaltabdefs && $externaltabdefs->valid()) {
                // Ensure delete tab remains last.
                $deletetab = null;
                if (!empty($this->tabs) && is_array($this->tabs) && $this->tabs[count($this->tabs) - 1]['tab_id'] == 'delete') {
                    $deletetab = array_pop($this->tabs);
                }
                foreach ($externaltabdefs as $externaltabdef) {
                    $tabdef = @unserialize($externaltabdef->tabdata);
                    if (empty($tabdef['tab_id']) || empty($tabdef['page'])) {
                        error_log("local_elisprogram_tab_defs: plugin = {$externaltabdef->plugin}; parent = {$externaltabdef->parent}; missing 'tab_id' and/or 'page' spec.");
                        continue;
                    }
                    if (!empty($tabdef['file']) && file_exists($CFG->dirroot.$tabdef['file'])) {
                        require_once($CFG->dirroot.$tabdef['file']);
                        $newtab = array();
                        $hasreqs = true;
                        foreach (array('params', 'name', 'showtab', 'showbutton') as $callableprop) {
                            if (!isset($tabdef[$callableprop])) {
                                $hasreqs = false;
                                break;
                            }
                            $callfcn = $tabdef[$callableprop];
                            if (is_array($callfcn)) {
                                switch ($callfcn[0]) {
                                    case 'this':
                                    case 'self':
                                        $callfcn[0] = new $tabdef['page'](($callableprop == 'params') ? array() : $newtab['params']);
                                        break;
                                }
                            }
                            if (!@is_callable($callfcn)) {
                                $hasreqs = false;
                                break;
                            }
                            $newtab[$callableprop] = call_user_func($callfcn, $externaltabdef->plugin, $externaltabdef->parent, $tabdef);
                        }
                        if (!$hasreqs) {
                            error_log("local_elisprogram_tab_defs: plugin = {$externaltabdef->plugin}; parent = {$externaltabdef->parent}; callable spec. error.");
                            continue;
                        }
                        $newtab['plugin'] = $externaltabdef->plugin;
                        $newtab['file'] = $tabdef['file'];
                        $newtab['tab_id'] = $tabdef['tab_id'];
                        $newtab['page'] = $tabdef['page'];
                        $newtab['image'] = !empty($tabdef['image']) ? $tabdef['image'] : false;
                        if (!is_array($this->tabs)) {
                            $this->tabs = array();
                        }
                        $this->tabs[] = $newtab;
                    } else {
                        error_log("local_elisprogram_tab_defs: plugin = {$externaltabdef->plugin}; parent = {$externaltabdef->parent}; 'file' = {$tabdef['file']} not found.");
                    }
                }
                if ($deletetab) {
                    $this->tabs[] = $deletetab;
                }
            }
        }
        parent::__construct($params);
    }

    protected function _get_page_url() {
        global $CFG;
        return "{$CFG->wwwroot}/local/elisprogram/index.php";
    }

    protected function _get_page_type() {
        return 'elispm';
    }

    protected function _get_page_params() {
        return array('s' => $this->pagename) + parent::_get_page_params();
    }

    function build_navbar_default($who = null) {
        global $CFG;
        if (!$who) {
            $who = $this;
        }
        parent::build_navbar_default();
        $who->navbar->add( /* is_siteadmin() */ (true)
                           ? get_string('programmanagement', 'local_elisprogram')
                           : get_string('learningplan', 'local_elisprogram'),
                          "{$CFG->wwwroot}/local/elisprogram/");
    }

    /**
     * Determines the name of the context class that represents this page's cm entity
     *
     * @return  string  The name of the context class that represents this page's cm entity
     *
     * @todo            Do something less complex to determine the appropriate class
     *                  (requires page class redesign)
     */
    function get_page_context() {
        $context = '';

        if (isset($this->parent_data_class)) {
            //parent data class is specified directly in the record
            $context = $this->parent_data_class;
        } else if (isset($this->parent_page) && isset($this->parent_page->data_class)) {
            //parent data class is specified indirectly through a parent page object
            $context = $this->parent_page->data_class;
        } else if (isset($this->tab_page)) {
            //a parent tab class exists
            $tab_page_class = $this->tab_page;

            //construct an instance of the named class and obtain its core data class
            $tab_page_class_instance = new $tab_page_class();
            $context = $tab_page_class_instance->data_class;
        } else if(isset($this->data_class)) {
            //out of other options, so directly use the data class associated with this page
            $context = $this->data_class;
        }

        return $context;
    }
}
