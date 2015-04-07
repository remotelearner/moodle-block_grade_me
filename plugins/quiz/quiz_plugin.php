<?php
global $CFG;
require_once($CFG->dirroot.'/question/engine/states.php');

require_once($CFG->dirroot . '/question/engine/lib.php');

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
    $query = ", qas.id step_id, qza.userid, qza.timemodified timesubmitted, qza.id submissionid, qas.sequencenumber
        FROM {quiz_attempts} qza
        JOIN {block_grade_me} bgm ON bgm.iteminstance = qza.quiz
        JOIN {question_attempts} qna ON qna.questionusageid = qza.uniqueid
        JOIN {question_attempt_steps} qas ON qas.userid = qza.userid AND qas.questionattemptid = qna.id
        JOIN (
            SELECT userid, questionattemptid, MAX(sequencenumber) as maxseqnum
              FROM {question_attempt_steps}
             WHERE userid $insql
          GROUP BY questionattemptid, userid
            ) maxsubq ON maxsubq.questionattemptid = qna.id
                     AND maxsubq.maxseqnum = qas.sequencenumber
                     AND maxsubq.userid = qas.userid
   LEFT JOIN {question_attempt_steps} qam ON qam.questionattemptid = qna.id
                                         AND qam.state IN('".question_state::$mangrright."',
                                                          '".question_state::$gradedright."',
                                                          '".question_state::$gradedpartial."',
                                                          '".question_state::$mangrpartial."',
                                                          '".question_state::$gradedwrong."',
                                                          '".question_state::$mangrwrong."')
       WHERE qza.state = '".question_state::$finished."'"."
         AND qna.behaviour = 'manualgraded'
         AND qza.timefinish != 0
         AND qam.sequencenumber IS NULL";

    return array($query, $inparams);
}
