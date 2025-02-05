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

function block_grade_me_required_capability_wholeforum() {
    $enabledplugins['wholeforum'] = array(
        'capability' => 'mod/forum:grade',
        'default_on' => false,
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabledplugins;
}

/**
 * Build SQL query for the forum plugin
 *
 * @param array $gradebookusers ID's of gradebook users
 * @return array|bool SQL query and parameters or false on failure
 */
function block_grade_me_query_wholeforum($gradebookusers) {
    global $USER, $DB;

    if (empty($gradebookusers)) {
        return false;
    }
    list($insql, $inparams) = $DB->get_in_or_equal($gradebookusers);

    $query = ", fp.id submissionid, fp.userid, fp.modified timesubmitted
        FROM {forum_posts} fp
        JOIN {forum_discussions} fd ON fd.id = fp.discussion
        JOIN {forum} f ON f.id = fd.forum
        JOIN {grade_items} gi ON gi.iteminstance = f.id
             AND gi.itemmodule = 'forum' AND gi.itemnumber = 1
   LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = fp.userid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = f.course AND bgm.iteminstance = f.id
       WHERE fp.userid $insql
         AND f.grade_forum != 0
         AND gg.finalgrade IS NULL ";

    return array($query, $inparams);
}
