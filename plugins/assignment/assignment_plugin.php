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
 * Grade Me Moodle 2.2+ assignment plugin.
 *
 * @package    block_grade_me
 * @copyright  2013 Dakota Duff {@link http://www.remote-learner.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @return array Specifics on the capabilities of the assignment plugin type
 */
function block_grade_me_required_capability_assignment() {
    $enabled_plugins['assignment'] = array(
        'capability' => 'mod/assignment:grade',
        'default_on' => true,
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabled_plugins;
}

/**
 * Build SQL query for the assignment plugin for Moodle 2.3 and later
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_assignment($gradebookusers) {
    global $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", asgn_sub.id submissionid, asgn_sub.userid, asgn_sub.timemodified timesubmitted
        FROM {assignment_submissions} asgn_sub
        JOIN {assignment} a ON a.id = asgn_sub.assignment
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = a.course AND bgm.iteminstance = a.id
       WHERE asgn_sub.userid $insql
             AND a.grade > 0
             AND asgn_sub.timemarked < asgn_sub.timemodified";

    return array($query, $inparams);
}
