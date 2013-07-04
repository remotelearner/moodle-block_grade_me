<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Grade Me block
 *
 * @package   block_grade_me
 * @copyright 2012 Dakota Duff
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2013022601;
$plugin->requires  = 2010112400; // See http://docs.moodle.org/dev/Moodle_Versions
$plugin->cron      = 3600;
$plugin->component = 'block_grade_me';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.4.4.3';

global $CFG;
$block_grade_me_plugins = get_list_of_plugins('blocks/grade_me/plugins');
foreach ($block_grade_me_plugins AS $block_grade_me_plugin) {
    if (file_exists($CFG->dirroot.'/blocks/grade_me/plugins/'.$block_grade_me_plugin.'/'.$block_grade_me_plugin.'_plugin.php')) {
        include_once($CFG->dirroot.'/blocks/grade_me/plugins/'.$block_grade_me_plugin.'/'.$block_grade_me_plugin.'_plugin.php');
        if (function_exists('block_grade_me_required_capability_'.$block_grade_me_plugin)) {
            $required_capability = 'block_grade_me_required_capability_'.$block_grade_me_plugin;
            $a = $required_capability();
            $plugin->dependencies['mod_'.$block_grade_me_plugin] = $a[$block_grade_me_plugin]['versiondependencies'];
        }
    }
}
