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
 * @package   block_grade_me
 * @copyright 2012 Dakota Duff
 */

/**
 * Returns CSV values from provided array
 * @param array $array The array to implode
 * @return string $string
 */
function block_grade_me_array2str($array) {
    if (count($array)) {
        $string = implode(',', $array);
    } else {
        $string = null;
    }
    return $string;
}

/**
 * Returns first portion of the SQL query for the Grade Me block
 *
 * @return string $query
 */
function block_grade_me_query_prefix() {
    $query = 'SELECT bgm.courseid, bgm.coursename, bgm.itemmodule, bgm.iteminstance, bgm.itemname, ' .
        'bgm.coursemoduleid, bgm.itemsortorder';
    return $query;
}

/**
 * Returns last portion of the SQL query for the Grade Me block
 *
 * @param string $mod The array to implode
 * @return string $string
 */
function block_grade_me_query_suffix($mod) {
    $query = " AND bgm.courseid = ?
 AND bgm.itemmodule = '$mod'";
    return $query;
}

/**
 * Returns the enabled Grade Me block plugins and their required capabilities
 * @return array $enabledplugins
 */
function block_grade_me_enabled_plugins() {
    global $CFG;
    $enabledplugins = array();
    $plugins = get_list_of_plugins('blocks/grade_me/plugins');
    foreach ($plugins as $plugin) {
        if (file_exists($CFG->dirroot.'/blocks/grade_me/plugins/'.$plugin.'/'.$plugin.'_plugin.php')
            && ($CFG->{'block_grade_me_enable'.$plugin} == true)) {
            include_once($CFG->dirroot.'/blocks/grade_me/plugins/'.$plugin.'/'.$plugin.'_plugin.php');
            if (function_exists('block_grade_me_required_capability_'.$plugin)) {
                $requiredcapability = 'block_grade_me_required_capability_'.$plugin;
                $enabledplugins = array_merge($enabledplugins, $requiredcapability());
            }
        }
    }
    return $enabledplugins;
}

/**
 * Returns CSV values from provided array
 * @param object $r The query result to parse
 * @return array $gradeables
 */
function block_grade_me_array($gradeables, $r) {
    $gradeables['meta']['courseid'] = $r->courseid;
    $gradeables['meta']['coursename'] = $r->coursename;
    $gradeables[$r->itemsortorder]['meta']['iteminstance'] = $r->iteminstance;
    $gradeables[$r->itemsortorder]['meta']['itemmodule'] = $r->itemmodule;
    $gradeables[$r->itemsortorder]['meta']['itemname'] = $r->itemname;
    $gradeables[$r->itemsortorder]['meta']['coursemoduleid'] = $r->coursemoduleid;
    $gradeables[$r->itemsortorder][$r->timesubmitted]['meta']['userid'] = $r->userid;
    $gradeables[$r->itemsortorder][$r->timesubmitted]['meta']['submissionid'] = $r->submissionid;
    if (isset($r->forum_discussion_id)) {
        $gradeables[$r->itemsortorder][$r->timesubmitted]['meta']['forum_discussion_id'] = $r->forum_discussion_id;
    }
    return($gradeables);
}

/**
 * Construct the tree of ungraded items
 * @param array $course The array of ungraded items for a specific course
 * @return string $text
 */
function block_grade_me_tree($course) {
    global $CFG, $DB, $OUTPUT, $SESSION;

    // Get time format string.
    $datetimestring = get_string('datetime', 'block_grade_me', array());
    // Grading image.
    $gradeimg = $CFG->wwwroot.'/blocks/grade_me/pix/check_mark.png';
    // Define text variable.
    $text = '';

    $courseid = $course['meta']['courseid'];
    $coursename = $course['meta']['coursename'];
    unset($course['meta']);

    $gradebooklink = $CFG->wwwroot.'/grade/report/index.php?id='.$courseid.'" title="'.
        get_string('link_gradebook_icon', 'block_grade_me', array('course_name' => $coursename));
    $altgradebook = get_string('alt_gradebook', 'block_grade_me', array('course_name' => $coursename));
    $gradebookicon = $OUTPUT->pix_icon('i/grades', $altgradebook, null, array('class' => 'gm_icon'));
    $courselink = $CFG->wwwroot.'/course/view.php?id='.$courseid;
    $coursetitle = get_string('link_gradebook', 'block_grade_me', array('course_name' => $coursename));
    $text .= '<div>';
    $text .= '<dt id="courseid'.$courseid.'" class="cmod">
    <div class="toggle open" onclick="$(\'dt#courseid'.$courseid.
        ' > div.toggle\').toggleClass(\'open\');$(\'dt#courseid'.$courseid.
        ' ~ dd\').toggleClass(\'block_grade_me_hide\');"></div>
    <a href="'.$gradebooklink.'">'.$gradebookicon.'</a>
    <a href="'.$courselink.'" title="'.$coursetitle.'">'.$coursename.'</a></dt>'."\n";
    $text .= "\n";

    ksort($course);

    foreach ($course as $item) {
        $itemmodule = $item['meta']['itemmodule'];
        $itemname = $item['meta']['itemname'];
        $coursemoduleid = $item['meta']['coursemoduleid'];
        unset($item['meta']);

        $modulelink = $CFG->wwwroot.'/mod/'.$itemmodule.'/view.php?id='.$coursemoduleid;
        $gradelink = $CFG->wwwroot;
        if ($itemmodule == 'assignment') {
            $gradelink .= '/mod/assignment/submissions.php?id='.$coursemoduleid;
        } else if ($itemmodule == 'quiz') {
            $gradelink .= '/mod/quiz/report.php?id='.$coursemoduleid;
        } else {
            $gradelink = $modulelink;
        }
        $moduletitle = get_string('link_mod', 'block_grade_me', array('mod_name' => $itemmodule));
        $moduleicon = $OUTPUT->pix_icon('icon', $moduletitle, $itemmodule, array('class' => 'gm_icon'));

        $text .= '<dd id="cmid'.$coursemoduleid.'" class="module">'."\n";  // Open module.
        $text .= '<div class="toggle" onclick="$(\'dd#cmid'.$coursemoduleid.
            ' > div.toggle\').toggleClass(\'open\');$(\'dd#cmid'.$coursemoduleid.
            ' > ul\').toggleClass(\'block_grade_me_hide\');"></div>'."\n";
        $text .= '<a href="'.$gradelink.'" title="'.$moduletitle.'">'.$moduleicon.'</a>';
        $text .= '<a href="'.$modulelink.'" title="'.$moduletitle.'">'.$itemname.'</a> ('.count($item).')'."\n";

        $text .= '<ul class="block_grade_me_hide">'."\n";

        ksort($item);

        // Assign module needs to have a rownum and useridlist.
        $rownum = 0;
        $useridlistid = $coursemoduleid.time();
        $useridlist = array();

        foreach ($item as $l3 => $submission) {
            $timesubmitted = $l3;
            $userid = $submission['meta']['userid'];
            $submissionid = $submission['meta']['submissionid'];

            $submissionlink = $CFG->wwwroot;
            if ($itemmodule == 'assignment') {
                $submissionlink .= '/mod/assignment/submissions.php?id='.$coursemoduleid.'&amp;userid=' . $userid .
                    '&amp;mode=single&amp;filter=0&amp;offset=0';
            } else if ($itemmodule == 'assign') {
                $submissionlink .= "/mod/assign/view.php?id=$coursemoduleid&action=grade&rownum=$rownum&useridlistid=$useridlistid";
                $rownum++;
                $useridlist[] = $userid;
            } else if ($itemmodule == 'data') {
                $submissionlink .= '/mod/data/view.php?rid='.$submissionid.'&amp;mode=single';
            } else if ($itemmodule == 'forum') {
                $forumdiscussionid = $submission['meta']['forum_discussion_id'];
                $submissionlink .= '/mod/forum/discuss.php?d='.$forumdiscussionid.'#p'.$submissionid;
            } else if ($itemmodule == 'glossary') {
                $submissionlink .= '/mod/glossary/view.php?id='.$coursemoduleid.'#postrating'.$submissionid;
            } else if ($itemmodule == 'quiz') {
                $submissionlink .= '/mod/quiz/review.php?attempt='.$submissionid;
            }

            unset($submission['meta']);

            $submissiontitle = get_string('link_grade_img', 'block_grade_me', array());
            $altmark = get_string('alt_mark', 'block_grade_me', array());

            $user = $DB->get_record('user', array('id' => $userid));

            $userfirst = $user->firstname;
            $userfirstlast = $user->firstname.' '.$user->lastname;
            $userprofiletitle = get_string('link_user_profile', 'block_grade_me', array('first_name' => $userfirst));

            $text .= '<li class="gradable">';  // Open gradable.
            $text .= '<a href="'.$submissionlink.'" title="'.$submissiontitle.'"><img src="'.$gradeimg.
                '" class="gm_icon" alt="'.$altmark.'" /></a>';  // Grade icon.
            $text .= $OUTPUT->user_picture($user, array('size' => 16, 'courseid' => $courseid, 'link' => true));
            $text .= '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'&amp;course='.
                $courseid.'" title="'.$userprofiletitle.'">'.$userfirstlast.'</a>';  // User name and profile link.
            $text .= '<br />'.userdate($timesubmitted, $datetimestring);  // Output submission date.
            $text .= '</li>'."\n";  // End gradable.
        }

        if ($itemmodule == 'assign') {
            $useridlistkey = $coursemoduleid.'_'.$useridlistid;
            $SESSION->mod_assign_useridlist[$useridlistkey] = $useridlist;
        }

        $text .= '</ul>'."\n";
        $text .= '</dd>'."\n";  // Close module.
    }
    $text .= '</div>';

    return $text;
}
// Reset table cron function.
function block_grade_me_cache_reset() {
    global $CFG, $DB;
    $DB->delete_records('block_grade_me');
    $DB->delete_records('block_grade_me_quiz_ngrade');
    block_grade_me_cache_grade_data();
    \block_grade_me\quiz_util::update_quiz_ngrade();
    set_config('cachedatalast', time(), 'reset_block');
}
// Main cron function.
function block_grade_me_cache_grade_data() {
    global $CFG, $DB;
    $lastrun = $DB->get_field('task_scheduled', 'lastruntime', array('classname' => 'cache_grade_data'));
    $params = array();
    $params['itemtype'] = 'mod';
    $enabledplugins = array_keys(block_grade_me_enabled_plugins());
    // Get the id for each plugin name.
    $enabledpluginsid = array();
    foreach ($enabledplugins as $plugin) {
        $enabledpluginsid[] = $DB->get_field('modules', 'id', array('name' => $plugin));
    }
    $timedif = time() - $lastrun;
    // Check the size of the grade me table. If its 0, then ignore time stamp.
    $tablesize = $DB->count_records('block_grade_me');
    if ($tablesize == '0') {
        $lastrun = '0';
    }
    // Get the list of all active courses in the database.
    $activeselect = "visible = 1";
    $courselist = $DB->get_records_select('course', $activeselect);
    foreach ($courselist as $actcourse) {
        $cid = $actcourse->id;
        $coursemod = $actcourse->timemodified;
        if ($lastrun == '0') {
            $coursemod = '0';
        } else {
            if ($coursemod > $lastrun) {
                // This handles the case if the course was hidden and made visible.
                $coursemod = '0';
            } else {
                $coursemod = $lastrun;
            }
        }
        // Validate the course has active users.
        $sqlcourse = "SELECT count(enrol.id)
                      FROM {user_enrolments} enrol
                      LEFT JOIN {user} user ON enrol.userid = user.id
                      LEFT JOIN {enrol} en ON enrol.enrolid = en.id
                      WHERE en.courseid = ?
                      AND user.deleted = 0";
        $validcourse = $DB->count_records_sql($sqlcourse, array('courseid' => $cid));
        if ($validcourse > '0') {
            $paramscourse =array();
            $paramscourse['itemtype'] = 'mod';

            $paramscourse['id'] = $cid;
            $paramscourse['timemodified'] = $coursemod;
            list($insql, $inparams) = $DB->get_in_or_equal($enabledpluginsid);
            $sql = "SELECT gi.id itemid, gi.itemname itemname, gi.itemtype itemtype,
                           gi.itemmodule itemmodule, gi.iteminstance iteminstance,
                           gi.sortorder itemsortorder, c.id courseid, c.shortname coursename,
                           cm.id coursemoduleid
                    FROM {grade_items} gi
               LEFT JOIN {course} c ON gi.courseid = c.id
               LEFT JOIN {modules} m ON m.name = gi.itemmodule
                    JOIN {course_modules} cm ON cm.course = c.id AND cm.module = m.id AND cm.instance = gi.iteminstance
                    WHERE gi.itemtype = ?
                          AND c.id = ?
                          AND gi.timemodified > ?
                          AND m.id $insql";
            $paramscourse = array_merge($paramscourse, $inparams);
            $rs = $DB->get_recordset_sql($sql, $paramscourse);
            foreach ($rs as $rec) {
                $values = array(
                    'itemtype'      => $rec->itemtype,
                    'itemmodule'    => $rec->itemmodule,
                    'iteminstance'  => $rec->iteminstance,
                    'courseid'      => $rec->courseid
                );
                $fragment = 'itemtype = :itemtype AND itemmodule = :itemmodule AND '.
                            'iteminstance = :iteminstance AND courseid = :courseid';
                $params = array(
                    'itemname' => $rec->itemname,
                    'itemtype' => $rec->itemtype,
                    'itemmodule' => $rec->itemmodule,
                    'iteminstance' => $rec->iteminstance,
                    'itemsortorder' => $rec->itemsortorder,
                    'courseid' => $rec->courseid,
                    'coursename' => $rec->coursename,
                    'coursemoduleid' => $rec->coursemoduleid,
                 );

                // Note: We use get_fieldset_select because duplicates may already exist.

                $ids = $DB->get_fieldset_select('block_grade_me', 'id', $fragment, $values);
                if (empty($ids)) {
                    $DB->insert_record('block_grade_me', $params);
                } else {
                    $params['id'] = reset($ids);
                    $DB->update_record('block_grade_me', $params);
                }
            }
        }
    }
    set_config('cachedatalast', time(), 'block_grade_me');
    return true;
}
