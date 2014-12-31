<?php

function block_grade_me_required_capability_lesson() {
    $enabled_plugins['lesson'] = array(
        'capability' => 'mod/lesson:grade', 
        'default_on' => false, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

/**
 * Build SQL query for the lesson plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_lesson($gradebookusers) {
    global $USER, $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    $concatid = $DB->sql_concat('latt.id', "'-'", $USER->id);
    $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

/**
 * Need to add some code to unserialize useranswer field in mdl_lesson_attempts table
 * so that we can check for score >0 for essay questions.
 *
 */
	//$essay =  '';
	//$essayinfo = unserialize($essay->useranswer);
	
    $query = ", latt.id submissionid, latt.userid, latt.timeseen timesubmitted
        FROM {lesson_pages} lp
        JOIN {lesson} l ON l.id = lp.lessonid
		JOIN {lesson_attempts} latt ON latt.lessonid = l.id
		JOIN {lesson_answers} lans ON lans.lessonid = l.id
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = l.course AND bgm.iteminstance = l.id
       WHERE latt.userid $insql
			 AND l.grade <> 0
			 AND latt.correct = 0
			 AND lp.qtype = 10
             ";

    return array($query, $inparams);
}
