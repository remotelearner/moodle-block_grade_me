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
        $quizattempts = get_quiz_attempts($quiz);
        $quizcontext = context_module::instance($cm->id);
        $attempts = get_question_attempts($quizcontext);

        foreach ($attemtps as $attempt) {
            $state = get_current_state_for_this_attempt($attempt->questionattemptid);
            if ($state == 'needsgrading') { 
                $quizstate = 'needsgrading';
            }
        }

        

        if ($quizstate == 'needsgrading') { 
            $courseid = $event->courseid;
            block_grade_me_observer::add_course_to_generate_cache_cron($event->courseid);
        }

        // The all logic of grade_me is actually not optimized for 2.7. We could something much simple and much performant.
        // We need to create the cache here. When a quiz is submitted and require grading then add the info to the cache 
        // for each teachers (i.e. person with the grade permission).
        // Then we need to add a new event observer when the quiz is graded. Then we should look for all the cache using this quiz/student,
        // and we should remove these entries from the cache. Then no need for any cron and no need for any massive search anywhere. 

    }

    /**
     * Triggered when assign attempt submission events are triggered.
     *
     * @param  \mod_assign\event\assessable_submitted $event 
     */
    public static function update_assign_cron_tasks(\mod_assign\event\assessable_submitted $event) {
        global $DB, $CFG;

        // Check that the assignment reuire grading.

        $courseid = $event->courseid;
        block_grade_me_observer::add_course_to_generate_cache_cron($event->courseid);
   }

}

