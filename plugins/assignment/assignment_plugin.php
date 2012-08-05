<?php

function block_grade_me_required_capability_assignment() {
    $enabled_plugins['assignment'] = array( 
        'capability' => 'mod/assignment:grade', 
        'default_on' => true, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

function block_grade_me_query_assignment($gradebookusers) {
    $query = '
            , `as`.`id` submissionid 
            , `as`.`userid` 
            , `as`.`timemodified` timesubmitted 
        FROM {assignment_submissions} `as` 
        INNER JOIN {assignment} `a` 
            ON `a`.`id` = `as`.`assignment`
        LEFT JOIN {block_grade_me} `bgm` 
            ON `bgm`.`courseid` = `a`.`course` 
            AND `bgm`.`iteminstance` = `a`.`id` 
        WHERE `as`.`userid` IN (\''.implode("','",$gradebookusers).'\') 
            AND `a`.`grade` > 0
            AND `as`.`timemarked` < `as`.`timemodified` 
        ';
    return $query;
}