<?php

function block_grade_me_required_capability_pcast() {
    $enabled_plugins['pcast'] = array(
        'capability' => 'mod/pcast:rate', 
        'default_on' => false, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

/**
 * Build SQL query for the pcast plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_pcast($gradebookusers) {
    global $USER, $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    $concatid = $DB->sql_concat('pe.id', "'-'", $USER->id);
    $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", pe.id submissionid, pe.userid, pe.timemodified timesubmitted, pe.id as pcast_entries_id
        FROM {pcast_episodes} pe
        JOIN {pcast} p ON p.id = pe.pcastid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = p.course AND bgm.iteminstance = p.id
       WHERE pe.userid $insql
			 AND p.scale <> 0
			 AND pe.timemodified >= pe.timecreated
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
