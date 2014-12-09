<?php
global $CFG;
require_once($CFG->dirroot.'/question/engine/states.php');

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

    // For a finished quiz question, get the last state it was in indicated by the maximum sequence number.
    // If the question was not manually or automatically graded then its yet to be graded.
    $query = ", qa.id state_id, qa.userid, qa.timemodified timesubmitted, q.id submissionid, qas.sequencenumber
        FROM {quiz_attempts} qa
        JOIN {quiz} q ON q.id = qa.quiz
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = q.course AND bgm.iteminstance = q.id
        JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
        JOIN (
            SELECT questionattemptid, MAX(sequencenumber) as maxseqnum
              FROM {question_attempt_steps}
          GROUP BY questionattemptid
            ) maxsubq ON maxsubq.questionattemptid = qa.id
       WHERE qa.userid $insql
         AND qa.state = '".question_state::$finished."'"."
         AND maxsubq.maxseqnum = qas.sequencenumber AND qa.timefinish != 0
         AND qas.state NOT IN('".question_state::$mangrright."',
                              '".question_state::$gradedright."',
                              '".question_state::$gradedwrong."',
                              '".question_state::$mangrwrong."')";

    return array($query, $inparams);
}
