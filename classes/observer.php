<?php

/**
 * Event observer for grade_me block plugin.
 *
 * @package    block_grade_me
 * @copyright  2015 VLACS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for grade_me block.
 */
class block_grade_me_observer {

    /**
     * Add the event course id to the list of course to invalid/regenerate cache during cron job.
     * @param int $courseid the course id
     */
    public static function add_course_to_generate_cache_cron($courseid) {
        $courseids = get_config('block_grade_me', 'generatecachecourseids');
        $courseidsarray = unserialize($courseids);
        if (empty($courseidsarray)) {
            $courseidsarray = array();
        }
        if (!in_array($courseid, $courseidsarray)) {
            $courseidsarray[] = $courseid;
            set_config('generatecachecourseids', serialize($courseidsarray), 'block_grade_me');
        }
    }

    /**
     * Triggered when quiz attempt submission events are triggered.
     *
     * @param  \mod_quiz\event\attempt_submitted $event 
     */
    public static function update_quiz_cron_tasks(\mod_quiz\event\attempt_submitted $event) {
        global $DB, $CFG;

        // Check that the quiz require grading.
        require_once($CFG->dirroot . '/blocks/grade_me/lib.php');

        //error_log(print_r($event, true));
        $eventdata = $event->get_data();

        // There is only on return quiz attempt.
        $quizattempt = $event->get_record_snapshot($eventdata['objecttable'], $eventdata['objectid']);

        // Get all question attempts for this quiz.
        $sql = "SELECT id , slot, questionid
                FROM {question_attempts}
                WHERE  questionusageid = :uniqueid
                ORDER BY slot ASC";
        $questionattemps = $DB->get_records_sql($sql, array('uniqueid' => $quizattempt->uniqueid));

        foreach ($questionattemps as $attempt) {
            $state = get_current_state_for_this_attempt($attempt->id);
            if ($state == 'needsgrading') {
                $quizstate = 'needsgrading';
            }
        }

        if ($quizstate == 'needsgrading') { 
            $courseid = $event->courseid;
            block_grade_me_observer::add_course_to_generate_cache_cron($event->courseid);
        }



        // TODO!!!
        // The all logic of grade_me is actually not optimized at all for 2.7. We could do something much simple and performant.
        // We actually need to edit the "cache" here, in the observer. When a quiz is submitted and requires grading then we add the info to the cache
        // for each teachers (i.e. users with the grade permission).
        // Then we need to add a new event observer when a essay question is manually graded. When this event is triggered,
        //  we should look for all the cache using this quiz/student,
        // and we should remove these entries from the cache. Then no need for any cron and no need for any massive search.
        // However this solution still needs to have a massive search to prefill the cache the first time the user access the block in a course
        // (Otherwise the teachers would only be aware of the quiz that has been submitted after the grade_me block is installed).
    }

    /**
     * Triggered when assign attempt submission events are triggered.
     *
     * @param  \mod_assign\event\assessable_submitted $event 
     */
    public static function update_assign_cron_tasks(\mod_assign\event\assessable_submitted $event) {
        global $DB, $CFG;

        // Check that the assignment require grading.
        $eventdata = $event->get_data();

        // There is only on return quiz attempt.
        $assignsubmission = $event->get_record_snapshot($eventdata['objecttable'], $eventdata['objectid']);

        // Check if grade exist
        if (!$DB->record_exists('assign_grades',
            array('assignment' => $assignsubmission->assignment, 'attemptnumber' => $assignsubmission->attemptnumber))) {
            $courseid = $event->courseid;
            block_grade_me_observer::add_course_to_generate_cache_cron($event->courseid);
        }
   }

}

