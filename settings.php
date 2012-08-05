<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    
    $settings->add(new admin_setting_configcheckbox('block_grade_me_enableadminviewall', get_string('settings_adminviewall', 'block_grade_me'), 
        get_string('settings_configadminviewall', 'block_grade_me'), 0));
    
    $settings->add(new admin_setting_configtext('block_grade_me_maxcourses', get_string('settings_maxcourses', 'block_grade_me'),
        get_string('settings_configmaxcourses', 'block_grade_me'), 10, PARAM_INT));
    
    $plugins = get_list_of_plugins('blocks/grade_me/plugins');
    foreach ($plugins AS $plugin) {
        if (file_exists($CFG->dirroot.'/blocks/grade_me/plugins/'.$plugin.'/'.$plugin.'_plugin.php')) {
            include_once($CFG->dirroot.'/blocks/grade_me/plugins/'.$plugin.'/'.$plugin.'_plugin.php');
            if (function_exists('block_grade_me_required_capability_'.$plugin)) {
                $required_capability = 'block_grade_me_required_capability_'.$plugin;
                $a = $required_capability();
                $settings->add(new admin_setting_configcheckbox('block_grade_me_enable'.$plugin, get_string('settings_enablepre', 'block_grade_me').' '.get_string('modulenameplural', 'mod_'.$plugin), 
                    get_string('settings_configenablepre', 'block_grade_me', array('plugin_name' => get_string('modulename', 'mod_'.$plugin))), $a[$plugin]['default_on']));
            }
        }
    } 
}

