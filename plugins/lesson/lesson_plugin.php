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
 * Required capabilities for the lesson plugin.
 *
 * @return array Array of required capability information.
 */
function block_grade_me_required_capability_lesson() {
    $enabledplugins['lesson'] = array(
        'capability' => 'mod/lesson:grade',
        'default_on' => false,
        'versiondependencies' => 'ANY_VERSION'
    );
    return $enabledplugins;
}

/**
 * Build SQL query for the lesson plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_lesson($gradebookusers) {
    global $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", la.id submissionid, la.userid, lans.timecreated timesubmitted
        FROM {lesson_attempts} la
        JOIN {lesson} l ON l.id = la.lessonid
        JOIN {lesson_answers} lans ON la.answerid = lans.id
        JOIN {lesson_pages} lp ON lp.lessonid = l.id AND lp.qtype = 10 AND la.pageid = lp.id
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = l.course AND bgm.iteminstance = l.id
   LEFT JOIN {lesson_grades} lg ON lg.lessonid = l.id AND lg.userid = la.userid
       WHERE la.userid $insql AND l.grade > 0 AND la.useranswer LIKE ?";
    $inparams[] = '%s:6:"graded";i:0%';
    return array($query, $inparams);
}
