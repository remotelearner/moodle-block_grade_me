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
        global $CFG;
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

        require_once($CFG->dirroot.'/blocks/grade_me/lib.php');
        $PAGE->requires->jquery();
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
            foreach (explode(',', $CFG->gradebookroles) AS $roleid) {
                $roleid = trim($roleid);
                if (groups_get_course_groupmode($course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                    $groups = groups_get_user_groups($courseid, $USER->id);
                    foreach ($groups[0] as $groupid) {
                        $gradebookusers = array_merge($gradebookusers, array_keys(get_role_users($roleid, $context, false, 'u.id', 'u.id ASC', null, $groupid)));
                    }
                } else {
                    $gradebookusers = array_merge($gradebookusers, array_keys(get_role_users($roleid, $context, false, 'u.id', 'u.id ASC')));
                }
            }

            $params['courseid'] = $courseid;

            foreach ($enabledplugins as $plugin => $a) {
                if (has_capability($a['capability'], $context)) {
                    $fn = 'block_grade_me_query_'.$plugin;
                    $pluginfn = $fn($gradebookusers);
                    if ($pluginfn !== false) {
                        list($sql, $inparams) = $fn($gradebookusers);
                        $query = block_grade_me_query_prefix().$sql.block_grade_me_query_suffix($plugin);
                        $values = array_merge($inparams, $params);
                        $rs = $DB->get_recordset_sql($query, $values);

                        foreach ($rs as $r) {
                            $gradeables = block_grade_me_array($gradeables, $r);
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
        foreach ($graderroles as $roleid => $value) {
            if (user_has_role_assignment($USER->id, $roleid) or is_siteadmin()) {
                $showempty = true;
            } else {
                $showempty = false;
            }
        }

        if (!empty($this->content->text)) {
             // Expand/Collapse button.
             $expand = '<button class="btn btn-mini btn-primary" type="button" onclick="togglecollapseall();">'.get_string('expand','block_grade_me').'</button>';

            $this->content->text = '<dl>'.$expand.$this->content->text.'<div class="excess">'.$additional.'</div></dl>';
        } else if (empty($this->content->text) && $showempty) {
            $this->content->text .= '<div class="empty">'.
                $OUTPUT->pix_icon('s/smiley', get_string('alt_smiley', 'block_grade_me')).' '.
                get_string('nothing', 'block_grade_me').'</div>'."\n";
        }

        return $this->content;
    }

    /**
     * cron - caches gradable items
     */
    public function cron() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/blocks/grade_me/lib.php');

        // We are going to measure execution times
        $starttime = microtime();

        $params = array();
        $params['itemtype'] = 'mod';
        $enabledplugins = array_keys(block_grade_me_enabled_plugins());

        list($insql, $inparams) = $DB->get_in_or_equal($enabledplugins);

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
            $values = array(
                'itemtype'      => $rec->itemtype,
                'itemmodule'    => $rec->itemmodule,
                'iteminstance'  => $rec->iteminstance,
                'courseid'      => $rec->courseid
            );
            $fragment = 'itemtype = :itemtype AND itemmodule = :itemmodule AND iteminstance = :iteminstance AND courseid = :courseid';
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

        // Show times
        mtrace('');
        mtrace('Updated block_grade_me cache in ' . microtime_diff($starttime, microtime()) . ' seconds');
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
