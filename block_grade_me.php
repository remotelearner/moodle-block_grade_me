<?php

class block_grade_me extends block_base {

    function init() {
        global $CFG;
        $this->title = get_string('pluginname','block_grade_me',array());
    }

    function get_content() {
        global $CFG, $USER, $COURSE, $DB, $OUTPUT, $PAGE;

        if ($this->content !== NULL) {
            return $this->content;
        }

        require_once($CFG->dirroot.'/blocks/grade_me/lib.php');
        $PAGE->requires->js('/blocks/grade_me/javascript/jquery-1.7.2.min.js');

        // create the content class
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

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
        } else {
            $courses[$COURSE->id] = $COURSE;
        }

        foreach ($courses AS $courseid => $course) {
            unset($params);
            $gradeables = array();
            $gradebookusers = array();
            $context = get_context_instance(CONTEXT_COURSE, $courseid);
            foreach (explode(',', $CFG->gradebookroles) AS $roleid) {
                if (groups_get_course_groupmode($course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                    $groups = groups_get_user_groups($courseid, $USER->id);
                    foreach ($groups[0] AS $groupid) {
                        $gradebookusers = array_merge($gradebookusers, array_keys(get_role_users($roleid, $context, false, 'u.id', 'NULL', null, $groupid)));
                    }
                } else {
                    $gradebookusers = array_merge($gradebookusers, array_keys(get_role_users($roleid, $context, false, 'u.id', 'NULL')));
                }
            }
            $params['courseid'] = $courseid;
            foreach ($enabled_plugins AS $plugin => $a) {
                if (has_capability($a['capability'], $context)) {
                    $query_plugin = 'block_grade_me_query_'.$plugin;
                    $query = block_grade_me_query_prefix().$query_plugin($gradebookusers).block_grade_me_query_suffix($plugin);
                    $rs = $DB->get_recordset_sql($query, $params);
                    foreach ($rs AS $r) $gradeables = block_grade_me_array($gradeables, $r);
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

        $query = '
            INSERT
            INTO {block_grade_me} (
                SELECT
                    gi.id `itemid`
                    , gi.itemname `itemname`
                    , gi.itemtype `itemtype`
                    , gi.itemmodule `itemmodule`
                    , gi.iteminstance `iteminstance`
                    , gi.sortorder `itemsortorder`
                    , c.id `courseid`
                    , c.shortname `coursename`
                    , cm.id `coursemoduleid`
                FROM {grade_items} gi
                LEFT JOIN {course} c
                    ON gi.courseid = c.id
                LEFT JOIN {modules} m
                    ON m.name = gi.itemmodule
                INNER JOIN {course_modules} cm
                    ON cm.`course` = c.`id`
                    AND cm.`module` = m.`id`
                    AND cm.`instance` = gi.`iteminstance`
                WHERE gi.`itemtype` = :itemtype
                    AND m.`name` IN (\''.implode("','",$enabled_plugins).'\')
                ORDER BY gi.id
            )
            ON DUPLICATE KEY UPDATE
                `itemname` = VALUES(`itemname`)
                , `itemtype` = VALUES(`itemtype`)
                , `itemmodule` = VALUES(`itemmodule`)
                , `iteminstance` = VALUES(`iteminstance`)
                , `itemsortorder` = VALUES(`itemsortorder`)
                , `courseid` = VALUES(`courseid`)
                , `coursename` = VALUES(`coursename`)
                , `coursemoduleid` = VALUES(`coursemoduleid`)
        ';
        print_r($query);
        $DB->execute($query, $params);

        // Show times
        mtrace('');
        mtrace('Updated block_grade_me cache in ' . microtime_diff($starttime, microtime()) . ' seconds');
    }

    // Moved outside of get_content per HOSSUP-1173 to fix displaying multiple block instances
    function applicable_formats() {
        return array('all' => true);
    }
}
