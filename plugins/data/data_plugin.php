<?php

function block_grade_me_required_capability_data() {
    $enabled_plugins['data'] = array(
        'capability' => 'mod/data:rate', 
        'default_on' => false, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

/**
 * Build SQL query for the data plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_data($gradebookusers) {
    global $USER, $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    $concatid = $DB->sql_concat('dr.id', "'-'", $USER->id);
    $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", dr.id submissionid, dr.userid, dr.timemodified timesubmitted
        FROM {data_records} dr
        JOIN {data} d ON d.id = dr.dataid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = d.course AND bgm.iteminstance = d.id
       WHERE dr.userid $insql
             AND d.assessed = 1
             AND $concatid NOT IN (
             SELECT $concatitem
               FROM {rating} r
              WHERE r.contextid IN (
                    SELECT cx.id
                      FROM {context} cx
                     WHERE cx.contextlevel = 70
                           AND cx.instanceid = bgm.coursemoduleid
                    )
             )";

    return array($query, $inparams);
}
