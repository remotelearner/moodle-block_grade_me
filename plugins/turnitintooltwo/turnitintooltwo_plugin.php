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
 * Required capabilities for the turnitintooltwo plugin.
 *
 * @return array Array of required capability information.
 */
function block_grade_me_required_capability_turnitintooltwo() {
    $enabledplugins['turnitintooltwo'] = array(
        'capability' => 'mod/turnitintooltwo:grade',
        'default_on' => false,
        'versiondependencies' => 'ANY_VERSION'
    );
    return $enabledplugins;
}

/**
 * Build SQL query for the turnitintooltwo plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_turnitintooltwo($gradebookusers) {
    global $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", ts.id submissionid, ts.userid, ts.submission_modified timesubmitted
        FROM {turnitintooltwo_submissions} ts
        JOIN {turnitintooltwo} t ON t.id = ts.turnitintooltwoid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = t.course AND bgm.iteminstance = t.id
       WHERE ts.userid $insql AND t.grade > 0 AND ts.submission_grade IS NOT NULL";
    return array($query, $inparams);
}
