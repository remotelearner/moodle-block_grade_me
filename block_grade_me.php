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
        $cache = cache::make('block_grade_me', 'blockhtml');
        //$cache->delete('content_' . $USER->id . '_' . $COURSE->id);
        $cachedata = $cache->get('content_' . $USER->id . '_' . $COURSE->id);

        if ($this->content !== NULL) {
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

    // except the first time a user access the grade_me block, the cache should never be empty.
    if (empty($cachedata)) {

        foreach ($courses AS $courseid => $course) {
            $gradeables = block_grade_me_get_course_gradeables($course, $USER); 
            $coursesgradeables[$courseid] = $gradeables;
         }

         $cache->set('content_' . $USER->id . '_' . $COURSE->id, $coursesgradeables);

    } else {
        $coursesgradeables = $cachedata;    
    }

       // Build the HTML code.
       foreach ($courses AS $courseid => $course) {
        
            if (count($coursesgradeables[$courseid]) > 0) {
                $coursecount++;
                if ($coursecount > $maxcourses) {
                    $additional = get_string('excess','block_grade_me', array('maxcourses' => $maxcourses));
                    break 1;
                } else {
                    ksort($coursesgradeables[$courseid]);
                    $this->content->text .= block_grade_me_tree($coursesgradeables[$courseid]);
                }
            }
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
        global $CFG, $DB, $SITE;
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


        // Moodle cache.
        $starttime =  microtime();


        $courseids = get_config('block_grade_me', 'generatecachecourseids');
        $courseidsarray = unserialize($courseids);

        $cache = cache::make('block_grade_me', 'blockhtml');
        $frontpagecaches = array();
        foreach($courseidsarray as $courseid) {
            // Retrieve all users who can grade.
            $assignusers = get_users_by_capability(context_course::instance($courseid), 'mod/assign:grade');
            $quizusers = get_users_by_capability(context_course::instance($courseid), 'mod/quiz:grade');
            $teachers = $assignusers + $quizusers;
            foreach ($teachers as $teacher) {
                // invalid the cache.
                $cache->delete('content_' . $teacher->id . '_' . $courseid);

                // regenerate the cache.
                $course = $DB->get_record('course', array('id' => $courseid));
                $gradeables = block_grade_me_get_course_gradeables($course, $teacher);
                $coursesgradeables = array($courseid => $gradeables);
                $cache->set('content_' . $teacher->id . '_' . $courseid, $coursesgradeables);

                // regenerate cache for front page.
                $frontpagecaches[$teacher->id] = $teacher->id;
            }
        }

        // Regenerate all front page caches of the impact teachers.
        foreach($frontpagecaches as $frontpagecache) {
             // invalid the cache.
                $cache->delete('content_' . $frontpagecache . '_' . $SITE->id);

                // regenerate the cache.
                $course = $DB->get_record('course', array('id' => $SITE->id));
                $gradeables = block_grade_me_get_course_gradeables($course, $teacher);
                $coursesgradeables = array($SITE->id => $gradeables);
                $cache->set('content_' . $frontpagecache . '_' . $SITE->id, $coursesgradeables);
        }

        // Delete the course id.
        set_config('generatecachecourseids', serialize(array()), 'block_grade_me');

        mtrace('');
        mtrace('Updated block_grade_me Moodle cache in ' . microtime_diff($starttime, microtime()) . ' seconds');
    }

    /**
     * The Grade Me block should only be available under a course context.
     *
     * @return array The formats which apply to this block
     */
    function applicable_formats() {
       return array('course' => true);
    }

    /**
     * Required in Moodle 2.4 to load /grade_me/settings.php file
     * @return bool Whether or not to include settings.php
     */
    function has_config() {
       return true;
    }
}

