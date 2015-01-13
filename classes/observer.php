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
        $courseid = $event->courseid;
        block_grade_me_observer::add_course_to_generate_cache_cron($event->courseid);
    }

    /**
     * Triggered when assign attempt submission events are triggered.
     *
     * @param  \mod_assign\event\assessable_submitted $event 
     */
    public static function update_assign_cron_tasks(\mod_assign\event\assessable_submitted $event) {
        global $DB, $CFG;
        $courseid = $event->courseid;
        block_grade_me_observer::add_course_to_generate_cache_cron($event->courseid);
   }

}

