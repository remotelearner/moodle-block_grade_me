<?php

function block_grade_me_required_capability_quiz() {
    $enabled_plugins['quiz'] = array( 
        'capability' => 'mod/quiz:grade', 
        'default_on' => true, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

function block_grade_me_query_quiz($gradebookusers) {
    $query = '
            , `qa`.`id` state_id 
            , `qa`.`userid` 
            , `qa`.`timemodified` timesubmitted 
        FROM {quiz_attempts} `qa`
        INNER JOIN {question_sessions} `qs` 
            ON `qs`.`attemptid` = `qa`.`id` 
        INNER JOIN {quiz} `q` 
            ON `q`.`id` = `qa`.`quiz`  
        INNER JOIN {quiz_question_instances} `qqi` 
            ON `qqi`.`quiz` = `q`.`id` 
            AND `qqi`.`question` = `qs`.`questionid` 
        LEFT JOIN {block_grade_me} `bgm` 
            ON `bgm`.`courseid` = `q`.`course` 
            AND `bgm`.`iteminstance` = `q`.`id` 
        WHERE `qa`.`userid` IN (\''.implode("','",$gradebookusers).'\') 
            AND `qa`.`timefinish` != 0
            AND `qqi`.`grade` > 0
            AND `qs`.`newgraded` != `qs`.`newest`
        ';
    return $query;
}