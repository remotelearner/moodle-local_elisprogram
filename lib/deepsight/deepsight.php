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
 * @copyright  (C) 2008-2015 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../lib/setup.php');
require_once(elispm::file('lib/deepsight/lib/lib.php'));

require_login();

$action = required_param('action', PARAM_TEXT);
$pagename = required_param('pagename', PARAM_TEXT);
$contextid = required_param('contextid', PARAM_INT);

$result = array('result' => 'success');
$classname = "deepsight_savedsearch";
if (class_exists("deepsight_savedsearch_".$pagename)) {
    // If tab has custom search, load it's class.
    $classname = "deepsight_savedsearch_".$pagename;
}
$savedsearch = new $classname(context::instance_by_id($contextid), $pagename);
switch ($action) {
    case 'search':
        $query = required_param('q', PARAM_TEXT);
        $result['results'] = $savedsearch->search($query);
        break;
    case 'save':
        $jsondata = required_param('searchdata', PARAM_RAW);
        $search = json_decode($jsondata);
        $result['id'] = $savedsearch->save($search);
        break;
    case 'delete':
        $id = required_param('id', PARAM_INT);
        $savedsearch->delete($id);
        break;
}
echo 'throw 1;'.json_encode($result);
