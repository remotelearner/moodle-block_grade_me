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

    $settings->add(new admin_setting_configtext('block_grade_me_maxage', get_string('settings_maxage', 'block_grade_me'),
        get_string('settings_configmaxage', 'block_grade_me'), 0, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('block_grade_me_enableshowhidden',
        get_string('settings_showhidden', 'block_grade_me'), get_string('settings_configshowhidden', 'block_grade_me'), 0));

    $plugins = get_list_of_plugins('blocks/grade_me/plugins');
    foreach ($plugins as $plugin) {
        if (file_exists($CFG->dirroot . '/blocks/grade_me/plugins/' . $plugin . '/' . $plugin . '_plugin.php')) {
            include_once($CFG->dirroot . '/blocks/grade_me/plugins/' . $plugin . '/' . $plugin . '_plugin.php');
            if (function_exists('block_grade_me_required_capability_' . $plugin)) {
                $requiredcapability = 'block_grade_me_required_capability_' . $plugin;
                $a = $requiredcapability();
                $component = 'mod_' . $plugin;
                if (\core_plugin_manager::instance()->get_plugin_info($component)) {
                    $langshowmod = get_string('settings_enablepre', 'block_grade_me');
                    $langshowmod .= ' ' . get_string('modulenameplural', $component);
                    $langmodname = get_string('modulename', $component);
                    $langshowdesc = get_string('settings_configenablepre', 'block_grade_me', ['plugin_name' => $langmodname]);
                    $settingname = 'block_grade_me_enable' . $plugin;
                    $default = (isset($a[$plugin]) && isset($a[$plugin]['default_on'])) ? $a[$plugin]['default_on'] : false;
                    $settings->add(new admin_setting_configcheckbox($settingname, $langshowmod, $langshowdesc, $default));
                }
            }
        }
    }

    $label = get_string('grade_me_tools', 'block_grade_me');
    $desc = get_string('grade_me_tools_desc', 'block_grade_me', $CFG->wwwroot);
    $settings->add(new admin_setting_heading('grade_me_tools', $label, $desc));
}
