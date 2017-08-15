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
 * @package block_grade_me
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2016 onwards Remote-Learner Inc (http://www.remote-learner.net)
 */

require_once(__DIR__.'/../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for block_grade_me.
 */
class behat_block_grade_me extends behat_base {

    /**
     * @Given /^the grade me block is present on all pages\.$/
     */
    public function the_grade_me_block_is_present_on_all_pages() {
        global $DB;
        $instancerec = (object)[
            'blockname' => 'grade_me',
            'parentcontextid' => 1,
            'showinsubcontexts' => 1,
            'pagetypepattern' => '*',
            'defaultregion' => 'side-post',
            'defaultweight' => '-2',
        ];
        $DB->insert_record('block_instances', $instancerec);
    }

    /**
     * Sets the specified value to the field with xpath.
     *
     * @Given /^I set the hidden field with xpath "(?P<fieldxpath_string>(?:[^"]|\\")*)" to "(?P<field_value_string>(?:[^"]|\\")*)"$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $field
     * @param string $value
     * @return void
     */
    public function i_set_the_hidden_field_with_xpath_to($fieldxpath, $value) {
        $fieldnode = $this->find('xpath', $fieldxpath);
        $field = behat_field_manager::get_form_field($fieldnode, $this->getSession());
        $field->set_value($value);
    }

    /**
     * Runs the cache_grade_data scheduled task immediately.
     *
     * This is copied from the "I run the scheduled task" step in M29+.
     *
     * @Given /^I run the grade me cache grade data scheduled task$/
     */
    public function i_run_the_grade_me_cache_grade_data_scheduled_task() {
        $taskname = 'block_grade_me\task\cache_grade_data';
        $task = \core\task\manager::get_scheduled_task($taskname);
        if (!$task) {
            throw new DriverException('The "' . $taskname . '" scheduled task does not exist');
        }

        // Do setup for cron task.
        raise_memory_limit(MEMORY_EXTRA);
        cron_setup_user();

        // Get lock.
        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
        if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
            throw new DriverException('Unable to obtain core_cron lock for scheduled task');
        }
        if (!$lock = $cronlockfactory->get_lock('\\' . get_class($task), 10)) {
            $cronlock->release();
            throw new DriverException('Unable to obtain task lock for scheduled task');
        }
        $task->set_lock($lock);
        if (!$task->is_blocking()) {
            $cronlock->release();
        } else {
            $task->set_cron_lock($cronlock);
        }

        try {
            // Discard task output as not appropriate for Behat output!
            ob_start();
            $task->execute();
            ob_end_clean();

            // Mark task complete.
            \core\task\manager::scheduled_task_complete($task);
        } catch (Exception $e) {
            // Mark task failed and throw exception.
            \core\task\manager::scheduled_task_failed($task);
            throw new DriverException('The "' . $taskname . '" scheduled task failed', 0, $e);
        }
    }
    /**
     * @Given /^I run the grade me reset block scheduled task$/
     */
    public function iRunTheGradeMeResetBlockScheduledTask()
    {
        $taskname = 'block_grade_me\task\reset_block';
        $task = \core\task\manager::get_scheduled_task($taskname);
        if (!$task) {
            throw new DriverException('The "' . $taskname . '" scheduled task does not exist');
        }

        // Do setup for cron task.
        raise_memory_limit(MEMORY_EXTRA);
        cron_setup_user();

        // Get lock.
        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
        if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
            throw new DriverException('Unable to obtain core_cron lock for scheduled task');
        }
        if (!$lock = $cronlockfactory->get_lock('\\' . get_class($task), 10)) {
            $cronlock->release();
            throw new DriverException('Unable to obtain task lock for scheduled task');
        }
        $task->set_lock($lock);
        if (!$task->is_blocking()) {
            $cronlock->release();
        } else {
            $task->set_cron_lock($cronlock);
        }

        try {
            // Discard task output as not appropriate for Behat output!
            ob_start();
            $task->execute();
            ob_end_clean();

            // Mark task complete.
            \core\task\manager::scheduled_task_complete($task);
        } catch (Exception $e) {
            // Mark task failed and throw exception.
            \core\task\manager::scheduled_task_failed($task);
            throw new DriverException('The "' . $taskname . '" scheduled task failed', 0, $e);
        }
    }

}
