<?php

function block_grade_me_required_capability_quiz() {
    $enabled_plugins['quiz'] = array( 
        'capability' => 'mod/quiz:grade', 
        'default_on' => true, 
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

/**
 * Build SQL query for the quiz plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_quiz($gradebookusers) {
    global $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", qa.id state_id, qa.userid, qa.timemodified timesubmitted
        FROM {quiz_attempts} qa
        JOIN {question_sessions} qs ON qs.attemptid = qa.id
        JOIN {quiz} q ON q.id = qa.quiz
        JOIN {quiz_question_instances} qqi ON qqi.quiz = q.id AND qqi.question = qs.questionid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = q.course AND bgm.iteminstance = q.id
       WHERE qa.userid $insql
             AND qa.timefinish != 0
             AND qqi.grade > 0
             AND qs.newgraded != qs.newest";

    return array($query, $inparams);
}
