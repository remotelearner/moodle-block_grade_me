<?php

function block_grade_me_required_capability_glossary() {
    $enabled_plugins['glossary'] = array( 
        'capability' => 'mod/glossary:rate', 
        'default_on' => false, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

function block_grade_me_query_glossary($gradebookusers) {
    global $USER;
    $query = '
            , `ge`.`id` submissionid 
            , `ge`.`userid` 
            , `ge`.`timemodified` timesubmitted 
        FROM {glossary_entries} `ge`
        INNER JOIN {glossary} `g` 
            ON `g`.`id` = `ge`.`glossaryid`
        LEFT JOIN {block_grade_me} `bgm` 
            ON `bgm`.`courseid` = `g`.`course` 
            AND `bgm`.`iteminstance` = `ge`.`id` 
        WHERE `ge`.`userid` IN (\''.implode("','",$gradebookusers).'\') 
            AND `g`.`assessed` = 1
            AND CONCAT(`ge`.`id`,\'-\','.$USER->id.') NOT IN (
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