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
 * Grade Me block.
 *
 * @package    block_grade_me
 * @copyright  2013 Dakota Duff {@link http://www.remote-learner.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_grade_me extends block_base {

    function init() {
        global $CFG;
        $this->title = get_string('pluginname','block_grade_me',array());
    }

    /**
     * This function does the work to query ungraded assignments according
     * to plugins present in the /grade_me/plugins directory which are
     * gradeable by the current user, and returns the block content to be
     * displayed.
     *
     * @return stdClass The content being rendered for this block
     */
    function get_content() {
        global $CFG, $USER, $COURSE, $DB, $OUTPUT, $PAGE;

        if ($this->content !== NULL) {
            return $this->content;
        }

        require_once($CFG->dirroot.'/blocks/grade_me/lib.php');
        $PAGE->requires->js('/blocks/grade_me/javascript/jquery-1.7.2.min.js');
        $PAGE->requires->js('/blocks/grade_me/javascript/grademe.js');

        // create the content class
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin()) {
            return $this->content;
        }

        // setup arrays
        $grader = array();
        $gradeables = array();

        $excess = false;
        $groups = NULL;

        $enabled_plugins = block_grade_me_enabled_plugins();

        $maxcourses = (isset($CFG->block_grade_me_maxcourses)) ? $CFG->block_grade_me_maxcourses : 10;
        $coursecount = 0;
        $additional = null;

        if ($COURSE->id == SITEID) {
            if (is_siteadmin() && $CFG->block_grade_me_enableadminviewall) {
                $courses = get_courses();
            }
            else {
                $courses = enrol_get_my_courses();
            }
            $iscoursecontext = false;
        } else {
            $courses[$COURSE->id] = $COURSE;
            $iscoursecontext = true;
        }

        // Expand/Collapse button.
        $this->content->text .= '<button class="btn btn-mini btn-primary" type="button" onclick="togglecollapseall('.$iscoursecontext.');">Collapse/Expand All</button>';

        foreach ($courses AS $courseid => $course) {
            unset($params);
            $gradeables = array();
            $gradebookusers = array();
            $context = context_course::instance($courseid);
            foreach (explode(',', $CFG->gradebookroles) AS $roleid) {
                if (groups_get_course_groupmode($course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                    $groups = groups_get_user_groups($courseid, $USER->id);
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

                                $quizattempts = $this->get_formatted_student_attempts($quiz, $cm);

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
            if (count($gradeables) > 0) {
                $coursecount++;
                if ($coursecount > $maxcourses) {
                    $additional = get_string('excess','block_grade_me', array('maxcourses' => $maxcourses));
                    break 1;
                } else {
                    ksort($gradeables);
                    $this->content->text .= block_grade_me_tree($gradeables);
                }
            }
            unset($gradeables);
        }

        $grader_roles = array();
        foreach ($enabled_plugins AS $plugin => $a) {
            foreach (array_keys(get_roles_with_capability($a['capability'])) AS $role) {
                $grader_roles[$role] = true;
            }
        }
        foreach ($grader_roles AS $roleid => $value) {
            if (user_has_role_assignment($USER->id, $roleid) or is_siteadmin()) {
                $showempty = true;
            } else {
                $showempty = false;
            }
        }

        if ($this->content->text) {
            $this->content->text = '<dl>'.$this->content->text.'<div class="excess">'.$additional.'</div></dl>';
        } elseif (!$this->content->text and $showempty) {
            $this->content->text .= '<div class="empty">'.$OUTPUT->pix_icon('s/smiley',get_string('alt_smiley','block_grade_me')).' '.get_string('nothing','block_grade_me').'</div>'."\n";
        }

        return $this->content;
    }


    /**
     * Return an array of quiz attempts
     * @param object $quiz
     * @return an array of userid
     */
    private function get_quiz_attempts($quiz) {
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
    private function get_question_attempts($quizcontext) {
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
    private function get_current_state_for_this_attempt($attemptid) {
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
    protected function get_formatted_student_attempts($quiz, $cm) {
        $quizattempts = $this->get_quiz_attempts($quiz);
        $quizcontext = context_module::instance($cm->id);
        $attempts = $this->get_question_attempts($quizcontext);
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
                    $state = $this->get_current_state_for_this_attempt($attempt->questionattemptid);
                    $questions[$attempt->slot]->state = $state;

                    if ($this->normalise_state($state) === 'needsgrading') {
                        $needsgrading++;
                    }
                    if ($this->normalise_state($state) === 'autograded') {
                        $autograded++;
                    }
                    if ($this->normalise_state($state) === 'manuallygraded') {
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
    protected function normalise_state($state) {
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
     * cron - caches gradable items
     */
    function cron() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/blocks/grade_me/lib.php');

        // We are going to measure execution times
        $starttime =  microtime();

        $params = array();
        $params['itemtype'] = 'mod';
        $enabled_plugins = array_keys(block_grade_me_enabled_plugins());

        list($insql, $inparams) = $DB->get_in_or_equal($enabled_plugins);

        $sql = "SELECT gi.id itemid, gi.itemname itemname, gi.itemtype itemtype,
                       gi.itemmodule itemmodule, gi.iteminstance iteminstance,
                       gi.sortorder itemsortorder, c.id courseid, c.shortname coursename,
                       cm.id coursemoduleid
                  FROM {grade_items} gi
             LEFT JOIN {course} c ON gi.courseid = c.id
             LEFT JOIN {modules} m ON m.name = gi.itemmodule
                  JOIN {course_modules} cm ON cm.course = c.id AND cm.module = m.id AND cm.instance = gi.iteminstance
                 WHERE gi.itemtype = ?
                       AND m.name $insql";

        $params = array_merge($params, $inparams);
        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $rec) {
            $uniquerecord = array(
                'itemtype'      => $rec->itemtype,
                'itemmodule'    => $rec->itemmodule,
                'iteminstance'  => $rec->iteminstance,
                'courseid'      => $rec->courseid
            );
            $idexists = $DB->record_exists('block_grade_me', $uniquerecord);
            $params = array(
                'id' => $rec->itemid,
                'itemname' => $rec->itemname,
                'itemtype' => $rec->itemtype,
                'itemmodule' => $rec->itemmodule,
                'iteminstance' => $rec->iteminstance,
                'itemsortorder' => $rec->itemsortorder,
                'courseid' => $rec->courseid,
                'coursename' => $rec->coursename,
                'coursemoduleid' => $rec->coursemoduleid,
            );
            if ($idexists) {
                $DB->update_record('block_grade_me', $params);
            } else {
                $DB->insert_record('block_grade_me', $params);
            }
        }

        // Show times
        mtrace('');
        mtrace('Updated block_grade_me cache in ' . microtime_diff($starttime, microtime()) . ' seconds');
    }

    /**
     * Moved outside of get_content per HOSSUP-1173 to fix
     * displaying multiple block instances within the same page
     * @return array The formats which apply to this block
     */
    function applicable_formats() {
       return array('all' => true);
    }

    /**
     * Required in Moodle 2.4 to load /grade_me/settings.php file
     * @return bool Whether or not to include settings.php
     */
    function has_config() {
       return true;
    }
}

