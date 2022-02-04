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

    public function init() {
        $this->title = get_string('pluginname', 'block_grade_me', array());
    }

    /**
     * This function does the work to query ungraded assignments according
     * to plugins present in the /grade_me/plugins directory which are
     * gradeable by the current user, and returns the block content to be
     * displayed.
     *
     * @return stdClass The content being rendered for this block
     */
    public function get_content() {
        global $CFG, $USER, $COURSE, $DB, $OUTPUT, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        require_once($CFG->dirroot . '/blocks/grade_me/lib.php');
        $PAGE->requires->jquery();
        $PAGE->requires->js('/blocks/grade_me/javascript/grademe.js');

        // Create the content class.
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin()) {
            return $this->content;
        }

        // Setup arrays.
        $gradeables = array();

        $groups = null;

        $enabledplugins = block_grade_me_enabled_plugins();

        $maxcourses = (isset($CFG->block_grade_me_maxcourses)) ? $CFG->block_grade_me_maxcourses : 10;
        $coursecount = 0;
        $additional = null;

        if ($COURSE->id == SITEID) {
            if (is_siteadmin() && $CFG->block_grade_me_enableadminviewall) {
                $courses = get_courses();
            } else {
                $courses = enrol_get_my_courses();
            }
        } else {
            $courses[$COURSE->id] = $COURSE;
        }

        foreach ($courses as $courseid => $course) {
            unset($params);
            $gradeables = array();
            $gradebookusers = array();
            $context = context_course::instance($courseid);
            foreach (explode(',', $CFG->gradebookroles) as $roleid) {
                $roleid = trim($roleid);
                if ((groups_get_course_groupmode($course) == SEPARATEGROUPS) &&
                    !has_capability('moodle/site:accessallgroups', $context)) {
                    $groups = groups_get_user_groups($courseid, $USER->id);
                    foreach ($groups[0] as $groupid) {
                        $gradebookusers = array_merge($gradebookusers,
                            array_keys(get_role_users($roleid, $context, false, 'u.id', 'u.id ASC', null, $groupid)));
                    }
                } else {
                    $gradebookusers = array_merge($gradebookusers,
                        array_keys(get_role_users($roleid, $context, false, 'u.id', 'u.id ASC')));
                }
            }

            $params['courseid'] = $courseid;

            foreach ($enabledplugins as $plugin => $a) {
                if (has_capability($a['capability'], $context)) {
                    $fn = 'block_grade_me_query_' . $plugin;
                    $pluginfn = $fn($gradebookusers);
                    if ($pluginfn !== false) {
                        list($sql, $inparams) = $fn($gradebookusers);
                        $query = block_grade_me_query_prefix() . $sql . block_grade_me_query_suffix($plugin);
                        $values = array_merge($inparams, $params);
                        $rs = $DB->get_recordset_sql($query, $values);

                        foreach ($rs as $r) {
                            if ($r->itemmodule == 'assign' && $r->maxattempts != '1') {
                                /* Check to be sure its the most recent attempt being graded */
                                $iteminstance = $r->iteminstance;
                                $userid = $r->userid;
                                $attemptnumber = $r->attemptnumber;
                                $sql = 'select MAX(attemptnumber) from {assign_submission} where assignment = ' . $iteminstance .
                                       ' and userid = ' . $userid;
                                $maxattempt = $DB->get_field_sql($sql);
                                if ($maxattempt == $attemptnumber) {
                                    $gradeables = block_grade_me_array($gradeables, $r);
                                }
                            } else {
                                $gradeables = block_grade_me_array($gradeables, $r);
                            }
                        }
                    }
                }
            }
            if (count($gradeables) > 0) {
                $coursecount++;
                if ($coursecount > $maxcourses) {
                    $additional = get_string('excess', 'block_grade_me', array('maxcourses' => $maxcourses));
                    break 1;
                } else {
                    ksort($gradeables);
                    $this->content->text .= block_grade_me_tree($gradeables);
                }
            }
            unset($gradeables);
        }

        $graderroles = array();
        foreach ($enabledplugins as $plugin => $a) {
            foreach (array_keys(get_roles_with_capability($a['capability'])) as $role) {
                $graderroles[$role] = true;
            }
        }
        $showempty = false;
        foreach ($graderroles as $roleid => $value) {
            if (user_has_role_assignment($USER->id, $roleid) || is_siteadmin()) {
                $showempty = true;
            }
        }

        if (!empty($this->content->text)) {
             // Expand/Collapse button.
             $expand = '<button class="btn btn-sm btn-outline-secondary" type="button" onclick="togglecollapseall();">' .
                get_string('expand', 'block_grade_me') . '</button>';

            $this->content->text = $expand . '<dl>' . $this->content->text . '</dl><div class="excess">' . $additional . '</div>';
        } else if (empty($this->content->text) && $showempty) {
            $this->content->text .= '<div class="excess">' . get_string('nothing', 'block_grade_me') . '</div>' . "\n";
        }

        return $this->content;
    }

    /**
     * The Grade Me block should only be available under a course context.
     *
     * @return array The formats which apply to this block
     */
    public function applicable_formats() {
        return array('all' => true);
    }

    /**
     * Required in Moodle 2.4 to load /grade_me/settings.php file
     * @return bool Whether or not to include settings.php
     */
    public function has_config() {
        return true;
    }
}
