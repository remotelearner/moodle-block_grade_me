<?php

function block_grade_me_required_capability_glossary() {
    $enabled_plugins['glossary'] = array(
        'capability' => 'mod/glossary:rate',
        'default_on' => false,
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

/**
 * Build SQL query for the glossy plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_glossary($gradebookusers) {
    global $USER, $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    $concatid = $DB->sql_concat('ge.id', "'-'", $USER->id);
    $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", ge.id submissionid, ge.userid, ge.timemodified timesubmitted
        FROM {glossary_entries} ge
        JOIN {glossary} g ON g.id = ge.glossaryid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = g.course AND bgm.iteminstance = ge.glossaryid
       WHERE ge.userid $insql
         AND g.assessed = 1
         AND g.scale <> 0
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
