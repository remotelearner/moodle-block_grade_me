<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Attempts observers.
 *
 * @package    block_grade_me
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2016 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace block_grade_me;

class quiz_observers {
    const DELETE_RECORDS_CHUNK = 10000;

    /**
     * Deletes records with specified IDs from block_grade_me_quiz_ngrade table
     * in chunks of self::DELETE_RECORDS_CHUNK to prevent timeouts in larger InnoDB tables.
     *
     * @param array $ids Array of record IDs.
     * @return void
     */
    private static function delete_from_ids( $ids ) {
        global $DB;
        if(empty($ids)) {
            return;
        }
        foreach (array_chunk($ids, self::DELETE_RECORDS_CHUNK) as $todelete) {
            list($idsql, $idparams) = $DB->get_in_or_equal($todelete);
            $DB->execute('
                DELETE FROM {block_grade_me_quiz_ngrade}
                WHERE id ' . $idsql,
                $idparams
            );
        }
    }

    /**
     * A course content has been deleted.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function course_content_deleted($event) {
        global $DB;
        $allids = $DB->get_records_sql_menu('
            SELECT DISTINCT id, id AS id2
            FROM {block_grade_me_quiz_ngrade}
            WHERE courseid = ?',
            [$event->courseid]
        );
        self::delete_from_ids($allids);
    }

    /**
     * A course content has been reset.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function course_reset_ended($event) {
        global $DB;
        if (!empty($event->other['reset_options']['reset_quiz_attempts'])) {
            $allids = $DB->get_records_sql_menu('
                SELECT DISTINCT id, id AS id2
                FROM {block_grade_me_quiz_ngrade}
                WHERE courseid = ?',
                [$event->other['reset_options']['courseid']]
            );
            self::delete_from_ids($allids);
        }
    }

    /**
     * A course module has been deleted.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function course_module_deleted($event) {
        global $DB;
        if ($event->other['modulename'] == 'quiz') {
            $allids = $DB->get_records_sql_menu('
                SELECT DISTINCT id, id AS id2
                FROM {block_grade_me_quiz_ngrade}
                WHERE quizid = ?',
                [$event->other['instanceid']]
            );
            self::delete_from_ids($allids);
        }
    }

    /**
     * An attempt has been deleted.
     *
     * @param \mod_quiz\event\attempt_deleted $event The event.
     * @return void
     */
    public static function attempt_deleted(\mod_quiz\event\attempt_deleted $event) {
        global $DB;
        // We need to convert the event's objectid (which is the quiz_attempts ID column) to the quiz_attempts uniqueid column.
        // Since this is a "deleted" event, we can't do a direct DB query, record snapshots to the rescue!
        try {
            $attemptrecord = $event->get_record_snapshot('quiz_attempts', $event->objectid);
            if (!empty($attemptrecord)) {
                $allids = $DB->get_records_sql_menu('
                    SELECT DISTINCT id, id AS id2
                    FROM {block_grade_me_quiz_ngrade}
                    WHERE attemptid = ?',
                    [$attemptrecord->uniqueid]
                );
                self::delete_from_ids($allids);
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * An attempt has been submitted.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function attempt_submitted($event) {
        \block_grade_me\quiz_util::update_quiz_ngrade($event->objectid);
    }

    /**
     * An attempt has been manually graded.
     *
     * @param \mod_quiz\event\question_manually_graded $event The event.
     * @return void
     */
    public static function question_manually_graded(\mod_quiz\event\question_manually_graded $event) {
        global $DB;
        // Lookup uniqueid from quiz_attempts table.
        $record = $DB->get_record('quiz_attempts', ['id' => $event->other['attemptid']]);
        if (empty($record)) {
            return false;
        }
        $sql = "SELECT COUNT(*) attempts
                  FROM {question_attempt_steps} qas
                       JOIN {question_attempts} qna ON qas.questionattemptid    = qna.id
                       JOIN {quiz_attempts} qza     ON qna.questionusageid      = qza.uniqueid
                       JOIN (SELECT questionattemptid, MAX(qas1.sequencenumber) maxseq
                          FROM {question_attempt_steps} qas1, {question_attempts} qna1
                         WHERE qas1.questionattemptid = qna1.id
                               AND qna1.questionusageid = ?
                      GROUP BY questionattemptid) maxseq ON maxseq.questionattemptid     = qna.id
                                                            AND qas.sequencenumber       = maxseq.maxseq
                       JOIN {quiz} q ON q.id = qza.quiz
                 WHERE qas.state = 'needsgrading'";
        $count = $DB->get_record_sql($sql, [$record->uniqueid]);
        // Delete attempts if all questions are graded for attempt, leave other attempts by user for quiz untouched.
        if (empty($count->attempts)) {
            $allids = $DB->get_records_sql_menu('
                SELECT DISTINCT id, id AS id2
                FROM {block_grade_me_quiz_ngrade}
                WHERE attemptid = ? AND quizid = ?',
                [$record->uniqueid, $event->other['quizid']]
            );
            self::delete_from_ids($allids);
        }
    }
}
