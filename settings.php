<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    
    $settings->add(new admin_setting_configtext('block_grade_me_maxitems', get_string('settings_maxitems', 'block_grade_me'),
                   get_string('settings_configmaxitems', 'block_grade_me'), 200, PARAM_INT));
    
    $settings->add(new admin_setting_configcheckbox('block_grade_me_enableassignment', get_string('settings_enableassignment', 'block_grade_me'),
                   get_string('settings_configenableassignment', 'block_grade_me'), 1));
    
    $settings->add(new admin_setting_configcheckbox('block_grade_me_enabledata', get_string('settings_enabledata', 'block_grade_me'),
                   get_string('settings_configenabledata', 'block_grade_me'), 1));
    
    $settings->add(new admin_setting_configcheckbox('block_grade_me_enableforum', get_string('settings_enableforum', 'block_grade_me'),
                   get_string('settings_configenableforum', 'block_grade_me'), 1));
    
    $settings->add(new admin_setting_configcheckbox('block_grade_me_enableglossary', get_string('settings_enableglossary', 'block_grade_me'),
                   get_string('settings_configenableglossary', 'block_grade_me'), 1));
/*    
    $settings->add(new admin_setting_configcheckbox('block_grade_me_enablelesson', get_string('settings_enablelesson', 'block_grade_me'),
                   get_string('settings_configenablelesson', 'block_grade_me'), 1));
*/    
    $settings->add(new admin_setting_configcheckbox('block_grade_me_enablequiz', get_string('settings_enablequiz', 'block_grade_me'),
                   get_string('settings_configenablequiz', 'block_grade_me'), 1));
/*    
    $settings->add(new admin_setting_configcheckbox('block_grade_me_enableworkshop', get_string('settings_enableworkshop', 'block_grade_me'),
                   get_string('settings_configenableworkshop', 'block_grade_me'), 1));
*/    
}

