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
    $query = 'SELECT bgm.courseid, bgm.coursename, bgm.itemmodule, bgm.iteminstance, bgm.itemname, bgm.coursemoduleid, bgm.itemsortorder';
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
            and $CFG->{'block_grade_me_enable'.$plugin} == true) {
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

    // get time format string
    $datetimestring = get_string('datetime', 'block_grade_me', array());
    // Grading image
    $gradeimg = $CFG->wwwroot.'/blocks/grade_me/pix/check_mark.png';
    // Define text variable
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

    foreach ($course as $l2 => $item) {
        $iteminstance = $item['meta']['iteminstance'];
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
        $moduleimgtitle = get_string('link_mod_img', 'block_grade_me', array('mod_name' => $itemmodule));
        $moduletitle = get_string('link_mod', 'block_grade_me', array('mod_name' => $itemmodule));
        $moduleicon = $OUTPUT->pix_icon('icon', $moduletitle, $itemmodule, array('class' => 'gm_icon'));

        $text .= '<dd id="cmid'.$coursemoduleid.'" class="module">'."\n";  // open module
        $text .= '<div class="toggle" onclick="$(\'dd#cmid'.$coursemoduleid.
            ' > div.toggle\').toggleClass(\'open\');$(\'dd#cmid'.$coursemoduleid.
            ' > ul\').toggleClass(\'block_grade_me_hide\');"></div>'."\n";
        $text .= '<a href="'.$gradelink.'" title="'.$moduletitle.'">'.$moduleicon.'</a>';
        $text .= '<a href="'.$modulelink.'" title="'.$moduletitle.'">'.$itemname.'</a> ('.count($item).')'."\n";

        $text .= '<ul class="block_grade_me_hide">'."\n";

        ksort($item);

        // Assign module needs to have a rownum and useridlist
        $rownum = 0;
        $useridlistid = $coursemoduleid.time();
        $useridlist = array();

        foreach ($item as $l3 => $submission) {
            $timesubmitted = $l3;
            $userid = $submission['meta']['userid'];
            $submissionid = $submission['meta']['submissionid'];

            $submissionlink = $CFG->wwwroot;
            if ($itemmodule == 'assignment') {
                $submissionlink .= '/mod/assignment/submissions.php?id='.$coursemoduleid.'&amp;userid='.$userid.'&amp;mode=single&amp;filter=0&amp;offset=0';
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

            $text .= '<li class="gradable">';  // open gradable
            $text .= '<a href="'.$submissionlink.'" title="'.$submissiontitle.'"><img src="'.$gradeimg.
                '" class="gm_icon" alt="'.$altmark.'" /></a>';  // grade icon
            $text .= $OUTPUT->user_picture($user, array('size' => 16, 'courseid' => $courseid, 'link' => true));
            $text .= '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'&amp;course='.
                $courseid.'" title="'.$userprofiletitle.'">'.$userfirstlast.'</a>';  // user name and profile link
            $text .= '<br />'.userdate($timesubmitted, $datetimestring);  // output submission date
            $text .= '</li>'."\n";  // end gradable
        }

        if ($itemmodule == 'assign') {
            $useridlistkey = $coursemoduleid.'_'.$useridlistid;
            $SESSION->mod_assign_useridlist[$useridlistkey] = $useridlist;
        }

        $text .= '</ul>'."\n";
        $text .= '</dd>'."\n";  // close module
    }
    $text .= '</div>';

    return $text;
}
