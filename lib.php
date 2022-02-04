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
    $query = 'SELECT * FROM (SELECT bgm.courseid, bgm.coursename, bgm.itemmodule, bgm.iteminstance, bgm.itemname, ' .
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
    $query = " AND bgm.courseid = ? AND bgm.itemmodule = '$mod') allitems";
    $maxage = get_config(null, 'block_grade_me_maxage');
    if (!empty($maxage) && is_numeric($maxage)) {
        $maxtimesubmitted = time() - ((int)$maxage * DAYSECS);
        $query .= " WHERE allitems.timesubmitted >= " . $maxtimesubmitted;
    }
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
        $pluginfile = $CFG->dirroot . '/blocks/grade_me/plugins/' . $plugin . '/' . $plugin . '_plugin.php';
        if (file_exists($pluginfile)) {
            $enablekey = 'block_grade_me_enable' . $plugin;
            if (isset($CFG->$enablekey) && $CFG->$enablekey == true) {
                include_once($pluginfile);
                $requiredcapabilityfunc = 'block_grade_me_required_capability_' . $plugin;
                if (function_exists($requiredcapabilityfunc)) {
                    $enabledplugins = array_merge($enabledplugins, $requiredcapabilityfunc());
                }
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
    $gradeimg = $CFG->wwwroot . '/blocks/grade_me/pix/check_mark.png';
    // Define text variable.
    $text = '';

    $courseid = $course['meta']['courseid'];
    $coursename = $course['meta']['coursename'];
    unset($course['meta']);

    $gradebooklink = $CFG->wwwroot . '/grade/report/index.php?id=' . $courseid . '" title="' .
        get_string('link_gradebook_icon', 'block_grade_me', array('course_name' => $coursename));
    $altgradebook = get_string('alt_gradebook', 'block_grade_me', array('course_name' => $coursename));
    $gradebookicon = $OUTPUT->pix_icon('i/grades', $altgradebook, null, array('class' => 'gm_icon'));
    $courselink = $CFG->wwwroot . '/course/view.php?id=' . $courseid;
    $coursetitle = get_string('link_gradebook', 'block_grade_me', array('course_name' => $coursename));
    $text .= '<div><dt id="courseid' . $courseid . '" class="cmod">
                <div tabindex=0 class="toggle open fa fa-caret-right" aria-hidden="true"
                    onclick="$(\'dt#courseid' . $courseid . ' > div.toggle\')
                    .toggleClass(\'open\');$(\'dt#courseid' . $courseid . ' ~ dd\')
                    .toggleClass(\'block_grade_me_hide\');">
                        <span class="sr-only">Toggle Section</span>
                </div>
                <a href="' . $courselink . '" class="grademe-course-name"' . $coursetitle . '">' . $coursename . '</a>
              </dt>' . "\n";
    $text .= "\n";

    ksort($course);

    foreach ($course as $item) {
        $itemmodule = $item['meta']['itemmodule'];
        $itemname = $item['meta']['itemname'];
        $coursemoduleid = $item['meta']['coursemoduleid'];
        unset($item['meta']);

        $modulelink = $CFG->wwwroot . '/mod/' . $itemmodule . '/view.php?id=' . $coursemoduleid;
        $gradelink = $CFG->wwwroot;
        if ($itemmodule == 'assignment') {
            $gradelink .= '/mod/assignment/submissions.php?id=' . $coursemoduleid;
        } else if ($itemmodule == 'quiz') {
            $gradelink .= '/mod/quiz/report.php?id=' . $coursemoduleid;
        } else {
            $gradelink = $modulelink;
        }
        $moduletitle = get_string('link_mod', 'block_grade_me', array('mod_name' => $itemmodule));
        $moduleicon = $OUTPUT->pix_icon('icon', $moduletitle, $itemmodule, array('class' => 'gm_icon'));

        $text .= '<dd id="cmid' . $coursemoduleid . '" class="module">' . "\n";  // Open module.
        $text .= '<div class="dd-wrap">' . "\n";
        $text .= '<div tabindex=0 class="toggle fa fa-caret-right" aria-hidden="true"
                    onclick="$(\'dd#cmid' . $coursemoduleid . ' > div div.toggle\')
                    .toggleClass(\'open\');$(\'dd#cmid' . $coursemoduleid . ' > ul\')
                    .toggleClass(\'block_grade_me_hide\');">
                        <span class="sr-only">Toggle Section</span>
                    </div>' . "\n";
        $text .= '<a href="' . $gradelink . '" class="grademe-course-icon" title="'
                 . $moduletitle . '">' . $moduleicon . '</a>' . "\n";
        $text .= '<a href="' . $modulelink . '" class="grademe-mod-name" title="' . $moduletitle . '">' . $itemname . '</a>' . "\n";
        $text .= '<span class="badge badge-pill badge-primary">' . count($item) . '</span>' . "\n";
        $text .= '</div>' . "\n";
        $text .= '<ul class="gradable-list block_grade_me_hide">' . "\n";

        ksort($item);

        foreach ($item as $l3 => $submission) {
            $timesubmitted = $l3;
            $userid = $submission['meta']['userid'];
            $submissionid = $submission['meta']['submissionid'];

            $submissionlink = $CFG->wwwroot;
            if ($itemmodule == 'assignment') {
                $submissionlink .= '/mod/assignment/submissions.php?id=' . $coursemoduleid . '&amp;userid=' . $userid .
                    '&amp;mode=single&amp;filter=0&amp;offset=0';
            } else if ($itemmodule == 'assign') {
                $submissionlink .= "/mod/assign/view.php?id=$coursemoduleid&action=grade&userid=$userid";
            } else if ($itemmodule == 'data') {
                $submissionlink .= '/mod/data/view.php?rid=' . $submissionid . '&amp;mode=single';
            } else if ($itemmodule == 'forum') {
                $forumdiscussionid = $submission['meta']['forum_discussion_id'];
                $submissionlink .= '/mod/forum/discuss.php?d=' . $forumdiscussionid . '#p' . $submissionid;
            } else if ($itemmodule == 'glossary') {
                $submissionlink .= '/mod/glossary/view.php?id=' . $coursemoduleid . '#postrating' . $submissionid;
            } else if ($itemmodule == 'quiz') {
                $submissionlink .= '/mod/quiz/review.php?attempt=' . $submissionid;
            } else if ($itemmodule == 'lesson') {
                $submissionlink .= '/mod/lesson/essay.php?id=' . $coursemoduleid . '&mode=grade&attemptid='
                                   . $submissionid . '&sesskey=' . sesskey();
            }

            unset($submission['meta']);

            $submissiontitle = get_string('link_grade_img', 'block_grade_me', array());
            $altmark = get_string('alt_mark', 'block_grade_me', array());

            $user = $DB->get_record('user', array('id' => $userid));

            $userfirst = $user->firstname;
            $userfirstlast = $user->firstname . ' ' . $user->lastname;
            $userprofiletitle = get_string('link_user_profile', 'block_grade_me', array('first_name' => $userfirst));

            $text .= '<li class="gradable">';  // Open gradable.
            $text .= '<a class="gradable-icon" href="' . $submissionlink . '" title="' . $submissiontitle . '">
                        <i class="fa fa-check" aria-hidden="true"></i>
                        <span class="sr-only">' . $submissiontitle . '</span>
                      </a>';
            $text .= '<div class="gradable-wrap">';
            $text .= '<a class="gradable-user" href="' . $CFG->wwwroot . '/user/view.php?id=' . $userid
                     . '&amp;course=' . $courseid . '" title="' . $userprofiletitle . '">';
            $text .= $userfirstlast;
            $text .= '</a>';
            $text .= '<div class="gradable-date">' . userdate($timesubmitted, $datetimestring) . '</div>';
            $text .= '</div>';
            $text .= '</li>' . "\n";  // End gradable.
        }

        $text .= '</ul>' . "\n";
        $text .= '</dd>' . "\n";  // Close module.
    }

    $text .= '</div>' . "\n";

    return $text;
}
// Reset table cron function.
function block_grade_me_cache_reset() {
    global $CFG, $DB;
    $DB->delete_records('block_grade_me');
    $DB->delete_records('block_grade_me_quiz_ngrade');
    block_grade_me_cache_grade_data();
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

    // See if the block has been added course wide.
    $paramsystem = array('site-index', 'my-index', '*');
    $sqlsystem = "SELECT count(b.id) bcount
                   FROM {block_instances} b
                   WHERE b.blockname = 'grade_me'
                  AND (b.pagetypepattern = ? or b.pagetypepattern = ?
                       or b.pagetypepattern = ?)";
    $systemblock = $DB->get_record_sql($sqlsystem, $paramsystem);
    $systemcount = $systemblock->bcount;

    // Get the list of all active courses in the database.
    $paramscourse = array();
    if ($systemcount > '0') {
        $sqlactive = "SELECT c.id, c.timemodified
                       FROM {course} c";
    } else {
        $sqlactive = "SELECT c.id, c.timemodified
                       FROM {course} c
                       JOIN {context} x ON c.id = x.instanceid
                       JOIN {block_instances} b
                         ON (b.parentcontextid = x.id
                             AND b.blockname = 'grade_me')";
    }

    // Determine whether to show hidden courses based on config setting.
    if (false == get_config(null, 'block_grade_me_enableshowhidden')) {
        $sqlactive .= " WHERE c.visible = '1'";
    }

    $courselist = $DB->get_recordset_sql($sqlactive, $paramscourse);
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
                   LEFT JOIN {user} u ON enrol.userid = u.id
                   LEFT JOIN {enrol} en ON enrol.enrolid = en.id
                       WHERE en.courseid = ?
                         AND u.deleted = 0";
        $validcourse = $DB->count_records_sql($sqlcourse, array('courseid' => $cid));
        if ($validcourse > '0') {
            $paramscourse = array();
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
                $fragment = 'itemtype = :itemtype AND itemmodule = :itemmodule AND ' .
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
            //
            // Build the quiz table per course. Cannot do this in bulk
            // because temp tables can cause large disk usage.
            // First get the list of quiz attempts for a course with manualgraded questions,
            // and that have active students in them.
            //

            $sqlquizlist = "SELECT mq.id quizid, mqa.id quiattemptid, mqa.userid, mq.course, mqa.uniqueid,
                            qna.id questionattemptid
                            FROM {quiz} mq
                            JOIN {quiz_attempts} mqa ON mqa.quiz = mq.id
                            JOIN {question_attempts} qna ON qna.questionusageid = mqa.uniqueid
                            JOIN {user} mu  ON mu.id = mqa.userid
                            WHERE course = ?
                            AND behaviour = 'manualgraded'
                            AND mu.deleted = 0";
            $paramsquiz = array($cid);
            $rsq = $DB->get_recordset_sql($sqlquizlist, $paramsquiz);
            foreach ($rsq as $recattempt) {
                $questionattemptid = $recattempt->questionattemptid;
                $sqlcount = "SELECT count(sequencenumber) mseq
                             FROM {question_attempt_steps}
                             WHERE questionattemptid = ? and state ='needsgrading'";
                $paramsteps = array($questionattemptid);
                $gradingneeded = $DB->count_records_sql($sqlcount, $paramsteps);
                if ($gradingneeded > '0') {
                    $sqlsteps = "SELECT max(sequencenumber) mseq
                             FROM {question_attempt_steps}
                             WHERE questionattemptid = ? and state ='needsgrading'";
                    $rsattempts = $DB->get_record_sql($sqlsteps, $paramsteps);
                    $maxseq = $rsattempts->mseq;
                    $needsgrading = '0';
                    if (!empty($maxseq)) {
                        $sqlmax = "SELECT max(sequencenumber) mseq2
                                   FROM {question_attempt_steps}
                                   WHERE questionattemptid = ?";
                        $rsmax = $DB->get_record_sql($sqlmax, $paramsteps);
                        $maxattempt = $rsmax->mseq2;
                        if ($maxattempt == $maxseq) {
                            $needsgrading = '1';
                        }
                    }
                    if ($needsgrading == '1') {
                        $quizid = $recattempt->quizid;
                        $sqlstepid = "SELECT id FROM {question_attempt_steps} WHERE questionattemptid = ?
                                      AND sequencenumber = ? and state = 'needsgrading'";
                        $paramsstepid = array($questionattemptid, $maxseq);
                        $rstepid = $DB->get_record_sql($sqlstepid, $paramsstepid);
                        $questionstepid = $rstepid->id;
                        $quizattemptid = $recattempt->uniqueid;
                        $courseid = $recattempt->course;
                        $userid = $recattempt->userid;
                        $sqlngrade = "INSERT INTO {block_grade_me_quiz_ngrade} ( attemptid, userid, quizid,
                                      questionattemptstepid, courseid ) VALUES( ?, ?, ?, ?, ?)";
                        $paramsngrade = array($quizattemptid, $userid, $quizid, $questionstepid, $courseid);
                        $DB->execute($sqlngrade, $paramsngrade);
                    }
                }
            }

        }
    }
    set_config('cachedatalast', time(), 'block_grade_me');
    return true;
}
