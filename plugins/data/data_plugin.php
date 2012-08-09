<?php

function block_grade_me_required_capability_data() {
    $enabled_plugins['data'] = array(
        'capability' => 'mod/data:rate', 
        'default_on' => false, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

function block_grade_me_query_data($gradebookusers) {
    global $USER;
    $query = '
            , `dr`.`id` submissionid 
            , `dr`.`userid` 
            , `dr`.`timemodified` timesubmitted 
        FROM {data_records} `dr` 
        INNER JOIN {data} `d` 
            ON `d`.`id` = `dr`.`dataid`
        LEFT JOIN {block_grade_me} `bgm` 
            ON `bgm`.`courseid` = `d`.`course` 
            AND `bgm`.`iteminstance` = `d`.`id` 
        WHERE `dr`.`userid` IN (\''.implode("','",$gradebookusers).'\') 
            AND `d`.`assessed` = 1
            AND CONCAT(`dr`.`id`,\'-\','.$USER->id.') NOT IN (
                SELECT CONCAT(`r`.`itemid`,\'-\',`r`.`userid`) 
                FROM {rating} `r` 
                WHERE `r`.`contextid` IN (
                    SELECT `cx`.`id` 
                    FROM {context} `cx` 
                    WHERE `cx`.`contextlevel` = 70 
                        AND `cx`.`instanceid` = `bgm`.`coursemoduleid`
                )
            )
        ';
    return $query;
}