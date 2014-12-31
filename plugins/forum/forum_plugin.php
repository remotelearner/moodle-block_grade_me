<?php

function block_grade_me_required_capability_forum() {
    $enabled_plugins['forum'] = array(
        'capability' => 'mod/forum:rate',
        'default_on' => false,
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

/**
 * Build SQL query for the forum plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_forum($gradebookusers) {
    global $USER, $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    $concatid = $DB->sql_concat('fp.id', "'-'", $USER->id);
    $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", fp.id submissionid, fp.userid, fp.modified timesubmitted, fd.id as forum_discussion_id
        FROM {forum_posts} fp
        JOIN {forum_discussions} fd ON fd.id = fp.discussion
        JOIN {forum} f ON f.id = fd.forum
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = f.course AND bgm.iteminstance = f.id
       WHERE fp.userid $insql
         AND f.assessed = 1
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
