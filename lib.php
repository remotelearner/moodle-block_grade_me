<?php
/**
 * @package   block_grade_me
 * @copyright 2012 Dakota Duff
 */

/**
 * Generate grade_me cache for a course/teacher.
 */
function block_grade_me_get_course_gradeables($course, $teacher) {
    global $DB, $CFG;

            $courseid = $course->id;
            $enabled_plugins = block_grade_me_enabled_plugins();
            unset($params);
            $gradeables = array();
            $gradebookusers = array();
            $context = context_course::instance($courseid);
            foreach (explode(',', $CFG->gradebookroles) AS $roleid) {
                if (groups_get_course_groupmode($course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                    $groups = groups_get_user_groups($courseid, $teacher->id);
                    foreach ($groups[0] AS $groupid) {
                        $gradebookusers = array_merge($gradebookusers, array_keys(get_role_users($roleid, $context, false, 'u.id', 'u.id ASC', null, $groupid)));
                    }
                } else {
                    $gradebookusers = array_merge($gradebookusers, array_keys(get_role_users($roleid, $context, false, 'u.id', 'u.id ASC')));
                }
            }

            $params['courseid'] = $courseid;

            foreach ($enabled_plugins AS $plugin => $a) {
                if (has_capability($a['capability'], $context)) {
                    $fn = 'block_grade_me_query_'.$plugin;
                    $pluginfn = $fn($gradebookusers);
                    if ($pluginfn !== false) {
                        // HACK: fix current quiz SQL that is broken.
                        // There are few additional functions created for this hack (they come from Tim Hunt's student grading quiz report)
                        // get_formated_student_attempts is calling oall of them.
                        // once the 2.7 branch is fixed on the github remotelearners (they will most likely fix the sql request in plugins/quiz/quiz_plugin.php), the you can overwritte these files.
                        // TODO on this current HACK: cache for the results, especially needed when the block is displayed on the front page
                        if ($plugin == 'quiz') {
                            // Get all quiz of the courses.
                            $course = $DB->get_record('course', array('id' => $courseid));
                            $quizs = get_all_instances_in_course("quiz", $course);

                            require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
                            $rs = array();
                            foreach ($quizs as $quiz) {
                                $cm = $DB->get_record('course_modules', array('id' => $quiz->coursemodule));

                                // This following line is highly performance hungry.
                                $quizattempts = get_formatted_student_attempts($quiz, $cm);

                                // Get item sortorder (order in the block)
                                $itemsortorder = $DB->get_field('block_grade_me', 'itemsortorder', array('coursemoduleid' => $cm->id));

                                foreach($quizattempts as $quizattempt) {
				    if ($quizattempt->needsgrading == 1) {
                                    $r = new stdClass();
                                    $r->courseid = $courseid;
                                    $r->coursename = $course->shortname;
                                    $r->itemmodule = 'quiz';
                                    $r->iteminstance = $quiz->id;
                                    $r->itemname = $quiz->name;
                                    $r->coursemoduleid = $cm->id;
                                    $r->userid = $quizattempt->userid;
                                    $r->submissionid = $quiz->id;
                                    $r->timesubmitted = $quizattempt->timefinish;
                                    $r->itemsortorder = $itemsortorder;

                                    $rs[] = $r;
				    }			
                                }
                            }
                        } else {
                            list($sql, $inparams) = $fn($gradebookusers);
                            $query = block_grade_me_query_prefix().$sql.block_grade_me_query_suffix($plugin);
                            $values = array_merge($inparams, $params);
                            $rs = $DB->get_recordset_sql($query, $values);
                        }

                        foreach ($rs as $r) {
                            $gradeables = block_grade_me_array($gradeables, $r);
                        }
                    }
                } 
           }

           return $gradeables;
}

    /**
     * Return an array of quiz attempts
     * @param object $quiz
     * @return an array of userid
     */
    function get_quiz_attempts($quiz) {
        global $DB;
        $quizid = $quiz->id;
        $sql = "SELECT qa.id AS attemptid, qa.uniqueid, qa.attempt AS attemptnumber, qa.quiz AS quizid, qa.layout,
                qa.userid, qa.timefinish, qa.preview, qa.state, u.idnumber
                From {user} u
                JOIN {quiz_attempts} qa ON u.id = qa.userid
                WHERE qa.quiz = $quizid AND qa.state = 'finished'
                ORDER BY u.idnumber ASC, attemptid ASC";
        $users = $DB->get_records_sql($sql);
        return $users;
    }

    /**
     * Return and array of question attempts
     * @return object, an array :
     */
    function get_question_attempts($quizcontext) {
        global $DB;
        $sql = "SELECT qa.id AS questionattemptid, qa.slot, qa.questionid, qu.id AS usageid
                FROM {question_usages} qu
                JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                WHERE qu.contextid = :contextid
                ORDER BY qa.slot ASC";
        return $DB->get_records_sql($sql, array('contextid' => $quizcontext->id));
    }

    /**
     * Reurn the latest state for a given question
     * @param int $attemptid
     */
    function get_current_state_for_this_attempt($attemptid) {
        global $DB;
        $sql = "SELECT qas.*
                FROM {question_attempt_steps} qas
                WHERE questionattemptid = $attemptid
                ORDER BY qas.sequencenumber ASC";
        $states = $DB->get_records_sql($sql);
        return end($states)->state;
    }

    /**
     * Return an array of quiz attempts withh all relevant information for each attempt
     *
     */
    function get_formatted_student_attempts($quiz, $cm) {
        $quizattempts = get_quiz_attempts($quiz);
        $quizcontext = context_module::instance($cm->id);
        $attempts = get_question_attempts($quizcontext);
        if (!$quizattempts) {
            return array();
        }
        if (!$attempts) {
            return array();
        }
        $output = array();
        foreach ($quizattempts as $key => $quizattempt) {
            $questions = array();
            $needsgrading = 0;
            $autograded = 0;
            $manuallygraded = 0;
            $all = 0;
            foreach ($attempts as $attempt) {
                if ($quizattempt->uniqueid === $attempt->usageid) {
                    $questions[$attempt->slot] = $attempt;
                    $state = get_current_state_for_this_attempt($attempt->questionattemptid);
                    $questions[$attempt->slot]->state = $state;

                    if (normalise_state($state) === 'needsgrading') {
                        $needsgrading++;
                    }
                    if (normalise_state($state) === 'autograded') {
                        $autograded++;
                    }
                    if (normalise_state($state) === 'manuallygraded') {
                        $manuallygraded++;
                    }
                    $all++;
                }
            }
            $quizattempt->needsgrading = $needsgrading;
            $quizattempt->autograded = $autograded;
            $quizattempt->manuallygraded = $manuallygraded;
            $quizattempt->all = $all;
            $quizattempt->questions = $questions;
            $output[$quizattempt->uniqueid] = $quizattempt;
        }
        return $output;
    }

    /**
     * Normalise the string from the database table for easy comparison
     * @param string $state
     */
    function normalise_state($state) {
        if (!$state) {
            return null;
        }
        if ($state === 'needsgrading') {
            return 'needsgrading';
        }
        if (substr($state, 0, strlen('graded')) === 'graded') {
            return 'autograded';
        }
        if (substr($state, 0, strlen('mangr')) === 'mangr') {
            return 'manuallygraded';
        }
        return null;
    }



/**
 * Returns CSV values from provided array
 * @param array $array The array to implode
 * @return string $string
 */
function block_grade_me_array2str($array) {
    if (count($array)) $string = implode(',',$array);
    else $string = NULL;
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
 * @return array $enabled_plugins
 */
function block_grade_me_enabled_plugins() {
    global $CFG;
    $enabled_plugins = array();
    $plugins = get_list_of_plugins('blocks/grade_me/plugins');
    foreach ($plugins AS $plugin) {
        if (file_exists($CFG->dirroot.'/blocks/grade_me/plugins/'.$plugin.'/'.$plugin.'_plugin.php')
            and $CFG->{'block_grade_me_enable'.$plugin} == true) {
            include_once($CFG->dirroot.'/blocks/grade_me/plugins/'.$plugin.'/'.$plugin.'_plugin.php');
            if (function_exists('block_grade_me_required_capability_'.$plugin)) {
                $required_capability = 'block_grade_me_required_capability_'.$plugin;
                $enabled_plugins = array_merge($enabled_plugins, $required_capability());
            }
        }
    }
    return $enabled_plugins;
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
    global $CFG, $OUTPUT, $DB;

    // get time format string
    $date_time_string = get_string('datetime', 'block_grade_me', array());
    // Grading image
    $gradeImg = $CFG->wwwroot.'/blocks/grade_me/pix/check_mark.png';
    // Define text variable
    $text = '';

    $courseid = $course['meta']['courseid'];
    $coursename = $course['meta']['coursename'];
    unset($course['meta']);

    $gradebooklink = $CFG->wwwroot.'/grade/report/index.php?id='.$courseid.'" title="'.get_string('link_gradebook_icon','block_grade_me',array('course_name' => $coursename));
    $altgradebook = get_string('alt_gradebook','block_grade_me',array('course_name' => $coursename));
    $gradebookicon = $OUTPUT->pix_icon('i/grades',$altgradebook,null,array('class' => 'gm_icon'));
    $courselink = $CFG->wwwroot.'/course/view.php?id='.$courseid;
    $coursetitle = get_string('link_gradebook','block_grade_me',array('course_name' => $coursename));
    $text .= '<div>';
    $text .= '<dt id="courseid'.$courseid.'" class="cmod">
    <div class="toggle" onclick="$(\'dt#courseid'.$courseid.' > div.toggle\').toggleClass(\'open\');$(\'dt#courseid'.$courseid.' ~ dd\').toggleClass(\'block_grade_me_hide\');"></div>
    <a href="'.$gradebooklink.'">'.$gradebookicon.'</a> 
    <a href="'.$courselink.'" title="'.$coursetitle.'">'.$coursename.'</a></dt>'."\n";
    $text .= "\n";

    ksort($course);

    foreach ($course AS $l2 => $item) {
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
        $moduleimgtitle = get_string('link_mod_img','block_grade_me',array('mod_name' => $itemmodule));
        $moduletitle = get_string('link_mod','block_grade_me',array('mod_name' => $itemmodule));
        $moduleicon = $OUTPUT->pix_icon('icon',$moduletitle,$itemmodule,array('class' => 'gm_icon'));

        $text .= '<dd id="cmid'.$coursemoduleid.'" class="module">'."\n";  //open module
        $text .= '<div class="toggle" onclick="$(\'dd#cmid'.$coursemoduleid.' > div.toggle\').toggleClass(\'open\');$(\'dd#cmid'.$coursemoduleid.' > ul\').toggleClass(\'block_grade_me_hide\');"></div>'."\n";
        $text .= '<a href="'.$gradelink.'" title="'.$moduletitle.'">'.$moduleicon.'</a>';
        $text .= '<a href="'.$modulelink.'" title="'.$moduletitle.'">'.$itemname.'</a> ('.count($item).')'."\n";

        $text .= '<ul class="block_grade_me_hide">'."\n";

        ksort($item);


        // Assign module needs to have a rownum
        $rownum = 0;

        foreach ($item AS $l3 => $submission) {
            $timesubmitted = $l3;
            $userid = $submission['meta']['userid'];
            $submissionid = $submission['meta']['submissionid'];

            $submissionlink = $CFG->wwwroot;
            if ($itemmodule == 'assignment') {
                $submissionlink .= '/mod/assignment/submissions.php?id='.$coursemoduleid.'&amp;userid='.$userid.'&amp;mode=single&amp;filter=0&amp;offset=0';
            } else if ($itemmodule == 'assign') {
                //$submissionlink .= "/mod/assign/view.php?id=$coursemoduleid&rownum=$rownum&action=grade&userid=$userid&item=$iteminstance";
				$submissionlink .= "/mod/assign/view.php?id=$coursemoduleid&action=grading";
                //$rownum++;
            } else if ($itemmodule == 'data') {
                $submissionlink .= '/mod/data/view.php?rid='.$submissionid.'&amp;mode=single';
            } else if ($itemmodule == 'forum') {
                $forumdiscussionid = $submission['meta']['forum_discussion_id'];
                $submissionlink .= '/mod/forum/discuss.php?d='.$forumdiscussionid.'#p'.$submissionid;
            } else if ($itemmodule == 'glossary') {
                $submissionlink .= '/mod/glossary/view.php?id='.$coursemoduleid.'#postrating'.$submissionid;
            } else if ($itemmodule == 'journal') {
                $submissionlink .= '/mod/journal/report.php?id='.$coursemoduleid;
			} else if ($itemmodule == 'lesson') {
                $submissionlink .= '/mod/lesson/essay.php?id='.$coursemoduleid;
			} else if ($itemmodule == 'pcast') {
                $submissionlink .= '/mod/pcast/showepisode.php?eid='.$submissionid;
            } else if ($itemmodule == 'quiz') {
                $submissionlink .= "/mod/quiz/report.php?id=".$coursemoduleid."&amp;mode=overview";
            }

            unset($submission['meta']);

            $submissiontitle = get_string('link_grade_img','block_grade_me',array());
            $altmark = get_string('alt_mark','block_grade_me',array());

            $user = $DB->get_record('user', array('id' => $userid));

            $userfirst = $user->firstname;
            $userfirstlast = $user->firstname.' '.$user->lastname;
            $userprofiletitle = get_string('link_user_profile','block_grade_me',array('first_name' => $userfirst));

            $text .= '<li class="gradable">';  // open gradable
            $text .= '<a href="'.$submissionlink.'" title="'.$submissiontitle.'"><img src="'.$gradeImg.'" class="gm_icon" alt="'.$altmark.'" /></a>';  // grade icon
            $text .= $OUTPUT->user_picture($user, array('size' => 16, 'courseid' => $courseid, 'link' => true));
            $text .= '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'&amp;course='.$courseid.'" title="'.$userprofiletitle.'">'.$userfirstlast.'</a>';  // user name and profile link
            $text .= '<br />'.userdate($timesubmitted,$date_time_string);  // output submission date
            $text .= '</li>'."\n";  // end gradable
        }

        $text .= '</ul>'."\n";
        $text .= '</dd>'."\n";  // close module
    }
    $text .= '</div>';
    return $text;
}
