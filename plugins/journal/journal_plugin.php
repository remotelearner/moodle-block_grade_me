<?php

function block_grade_me_required_capability_journal() {
    $enabled_plugins['journal'] = array(
        'capability' => 'mod/journal:manageentries', 
        'default_on' => false, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

/**
 * Build SQL query for the journal plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_journal($gradebookusers) {
    global $USER, $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    $concatid = $DB->sql_concat('je.id', "'-'", $USER->id);
    $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", je.id submissionid, je.userid, je.modified timesubmitted, je.id as journal_entries_id
        FROM {journal_entries} je
        JOIN {journal} j ON j.id = je.journal
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = j.course AND bgm.iteminstance = je.journal
       WHERE je.userid $insql
			 AND j.grade <> 0
			 AND (je.id IS NULL OR je.modified > je.timemarked)
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
