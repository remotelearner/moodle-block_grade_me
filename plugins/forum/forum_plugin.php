<?php

function block_grade_me_required_capability_forum() {
    $enabled_plugins['forum'] = array(
        'capability' => 'mod/forum:rate', 
        'default_on' => false, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

function block_grade_me_query_forum($gradebookusers) {
    global $USER;
    $query = '
            , `fp`.`id` submissionid 
            , `fp`.`userid` 
            , `fp`.`modified` timesubmitted 
        FROM {forum_posts} `fp` 
        INNER JOIN {forum_discussions} `fd` 
            ON `fd`.`id` = `fp`.`discussion`
        INNER JOIN {forum} `f` 
            ON `f`.`id` = `fd`.`forum`
        LEFT JOIN {block_grade_me} `bgm` 
            ON `bgm`.`courseid` = `f`.`course` 
            AND `bgm`.`iteminstance` = `f`.`id` 
        WHERE `fp`.`userid` IN (\''.implode("','",$gradebookusers).'\') 
            AND `f`.`assessed` = 1
            AND CONCAT(`fp`.`id`,\'-\','.$USER->id.') NOT IN (
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