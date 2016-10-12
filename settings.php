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

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configcheckbox('block_grade_me_enableadminviewall',
        get_string('settings_adminviewall', 'block_grade_me'), get_string('settings_configadminviewall', 'block_grade_me'), 0));

    $settings->add(new admin_setting_configtext('block_grade_me_maxcourses', get_string('settings_maxcourses', 'block_grade_me'),
        get_string('settings_configmaxcourses', 'block_grade_me'), 10, PARAM_INT));

    $plugins = get_list_of_plugins('blocks/grade_me/plugins');
    foreach ($plugins as $plugin) {
        if (file_exists($CFG->dirroot.'/blocks/grade_me/plugins/'.$plugin.'/'.$plugin.'_plugin.php')) {
            include_once($CFG->dirroot.'/blocks/grade_me/plugins/'.$plugin.'/'.$plugin.'_plugin.php');
            if (function_exists('block_grade_me_required_capability_'.$plugin)) {
                $requiredcapability = 'block_grade_me_required_capability_'.$plugin;
                $a = $requiredcapability();
                $settings->add(new admin_setting_configcheckbox('block_grade_me_enable'.$plugin,
                    get_string('settings_enablepre', 'block_grade_me').' '.get_string('modulenameplural', 'mod_'.$plugin),
                    get_string('settings_configenablepre', 'block_grade_me',
                        array('plugin_name' => get_string('modulename', 'mod_'.$plugin))), $a[$plugin]['default_on']));
            }
        }
    }

    $label = get_string('grade_me_tools', 'block_grade_me');
    $desc = get_string('grade_me_tools_desc', 'block_grade_me', $CFG->wwwroot);
    $settings->add(new admin_setting_heading('grade_me_tools', $label, $desc));
}
