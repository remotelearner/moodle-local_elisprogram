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
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

defined('MOODLE_INTERNAL') || die();

/**
 * ELIS tabs external definitions for Moodle plugins.
 * {component}/db/elistabs.php file format:
 *
$elistabs = array(
    '{parentpage}' // ELIS Program managementpage, e.g. coursepage, pmclasspage, curriculumpage, usersetpage, trackpage, coursesetpage, userpage, usersetclassificationpage;
    => array(
            array( // Single tab definition
                'tab_id' => '{uniquetabidforparentpage}',
                'file' => '{relative-to-dirroot}',
                'page' => '{elispageclass}',
                'params' => {callable+ spec. - returns array('param' => 'value', ...);},
                'name' => {callable+ spec. - returns string;},
                'showtab' => {callable+ spec. - returns bool;},
                'showbutton' => {callable+ spec. - returns bool;},
                'image' => '{plugin-pix-url-spec.}'
            ), ...
    ),
);

where:
    'file' => '{relative-to-dirroot}'; i.e. 'file' => '/local/mylocalplugin/myelispage.class.php'

    'page' => {elispageclass} must be a valid ELIS elis_page class.

    {callable+ spec.} is PHP callable with allow: 'this' or 'self', as first array argument, to use specified tab 'page' class;
    e.g.  'name' => array('this', 'get_tab_name'),

Also, the callable+ functions are pass the following parameters:
    callablefcn($plugin, $parent, $tabdata);
@param string $plugin The plugin frankenstyle name.
@param string $parent The parent ELIS page class.
@param array $tabdata The tab data.

*/
