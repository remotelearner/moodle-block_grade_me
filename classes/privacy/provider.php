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
 * Privacy Subsystem implementation for block_grade_me.
 *
 * @package    block_grade_me
 * @copyright  2019 Nathan Nguyen <nathannguyen@catalyst-net.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

/**
 * The block_grade_me does not store any data.
 *
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin currently implements the original plugin\provider interface.
    \core_privacy\local\request\plugin\provider {

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = [
            'contextuser'   => CONTEXT_USER,
            'userid'        => $userid
        ];

        $sql = "SELECT c.id
                  FROM {block_grade_me_quiz_ngrade} gme
                  JOIN {context} c ON c.instanceid = gme.userid AND c.contextlevel = :contextuser
                 WHERE gme.userid = :userid
              GROUP BY c.id";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $params = [
            'userid' => $userid
        ];

        $sql = "SELECT *
                  FROM {block_grade_me_quiz_ngrade} gme
                 WHERE gme.userid = :userid";

        $grademe = $DB->get_records_sql($sql, $params);

        $data = (object) [
            'grade_me' => $grademe,
        ];

        $subcontext = [
            get_string('pluginname', 'block_grade_me'),
            get_string('privacydata', 'block_grade_me')
        ];

        writer::with_context($context)->export_data($subcontext, $data);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $DB->delete_records('block_grade_me_quiz_ngrade', ['userid' => $userid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $DB->delete_records('block_grade_me_quiz_ngrade', ['userid' => $userid]);
    }

    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_grade_me_quiz_ngrade', [
            'userid' => 'privacy:metadata:block_grade_me_quiz_ngrade:userid',
            'quizid' => 'privacy:metadata:block_grade_me_quiz_ngrade:quizid',
            'questionattemptstepid' => 'privacy:metadata:block_grade_me_quiz_ngrade:questionattemptstepid',
            'courseid' => 'privacy:metadata:block_grade_me_quiz_ngrade:courseid',
            'attemptid' => 'privacy:metadata:block_grade_me_quiz_ngrade:attemptid',
        ], 'privacy:metadata:block_grade_me_quiz_ngrade');

        return $collection;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT * FROM {block_grade_me_quiz_ngrade}";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $users = $userlist->get_users();
        foreach ($users as $user) {
            // Create a contextlist with only system context.
            $contextlist = new approved_contextlist($user, 'block_grade_me', [\context_user::instance($user->id)->id]);
            self::delete_data_for_user($contextlist);
        }
    }

}
