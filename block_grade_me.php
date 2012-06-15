<?php // v3.1

class block_grade_me extends block_base {
    
    function init() {
        global $CFG;
        $this->title = get_string('pluginname','block_grade_me',array());
    }
    
    function array2str($array) {
        if (count($array)) $string = implode(',',$array);
        else $string = NULL;
        return $string;
    }

    function applicable_formats() {
        return array('all' => true);
    }

    function get_content() {
        if ($this->content !== NULL) {
            return $this->content;
        }
        
        global $COURSE, $CFG, $DB, $USER, $OUTPUT, $PAGE;
        
        $PAGE->requires->js('/blocks/grade_me/javascript/jquery-1.6.1.min.js');
        
        $isfrontpage = ($COURSE->id == SITEID);
        
        // create the content class
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';
        
        // setup arrays
        $grade_me_arr = array();
        $params = array();
        $supported_mods = array(
            'assignment' => 'mod/assignment:grade'
            , 'data' => 'mod/data:rate'
            , 'forum' => 'mod/forum:rate'
            , 'glossary' => 'mod/glossary:rate'
//            , 'lesson' => 'mod/lesson:manage'
            , 'quiz' => 'mod/quiz:grade'
//            , 'workshop' => 'mod/workshop:peerassess'
        );
        
        foreach ($supported_mods AS $mod => $permission) {
            $grader[$mod]['grouped'] = array();
            $grader[$mod]['nongrouped'] = array();
        }
        
        $allgroups = array();
        $separategroups = array();
        $count['grader'] = array();
        $ungraded_queries = array();
        
        $excess = false;
        $groups = NULL;
        
        // get time format string
        $date_time_string = get_string('datetime','block_grade_me',array());
        
        $maxitems = (isset($CFG->block_grade_me_maxitems)) ? $CFG->block_grade_me_maxitems : 200;
        foreach ($supported_mods AS $mod => $permission) {
            ${'enable'.$mod} = (isset(${'CFG->block_grade_me_enable'.$mod})) ? ${'CFG->block_grade_me_enable'.$mod} : 1;
        }
        
        
        if ($isfrontpage) {
            $query = "SELECT id, groupmode, groupmodeforce FROM {course}";
            $courses = $DB->get_recordset_sql($query);
            foreach ($courses AS $course) {
                $context = get_context_instance(CONTEXT_COURSE,$course->id);
                foreach ($supported_mods AS $mod => $permission) {
                    if (has_capability($permission,$context)  &&  ${'enable'.$mod}) $grader[$mod]['grouped'][] = $course->id;
                }
                if (has_capability('moodle/site:accessallgroups',$context)) $allgroups[] = $course->id;
                elseif ($course->groupmode == 1  &&  $course->groupmodeforce == 1) $separategroups[] = $course->id;
            }
        }
        else {
            // get the current context
            $context = get_context_instance(CONTEXT_COURSE,$COURSE->id);
            foreach ($supported_mods AS $mod => $permission) {
                if (has_capability($permission,$context)  &&  ${'enable'.$mod}) $grader[$mod]['grouped'][] = $COURSE->id;
            }
            if (has_capability('moodle/site:accessallgroups',$context)) $allgroups[] = $COURSE->id;
            elseif ($COURSE->groupmode == 1  &&  $COURSE->groupmodeforce == 1) $separategroups[] = $COURSE->id;
        }
        
        // create new arrays for instances that don't need to respect groups
        foreach ($supported_mods AS $mod => $permission) {
            if (count($grader[$mod]['grouped'])) $grader[$mod]['nongrouped'] = array_diff($grader[$mod]['grouped'],$separategroups);
        }
        // convert arrays to comma-delimited strings
        foreach ($supported_mods AS $mod => $permission) {
            if (count($grader[$mod]['grouped'])) $count['grader_grouped'][$mod] = count($grader[$mod]['grouped']); // We'll use this info later
            if (count($grader[$mod]['nongrouped'])) $count['grader'][$mod] = count($grader[$mod]['nongrouped']);
            $grader[$mod]['grouped'] = $this->array2str($grader[$mod]['grouped']); // Convert to strings for queries
            $grader[$mod]['nongrouped'] = $this->array2str($grader[$mod]['nongrouped']);
        }
        $allgroups = $this->array2str($allgroups);
        $separategroups = $this->array2str($separategroups);
        
        
        // get groups for forced separate courses
        if (isset($separategroups)  &&  (count($count['grader_grouped']))) {
            $groups = array();
            $query = "SELECT g.id id 
                        FROM {course} c 
                           , {groups} g 
                           , {groups_members} g_m 
                       WHERE c.id = g.courseid 
                         AND g.id = g_m.groupid 
                         AND g_m.userid = :userid
                         AND c.id IN ({$separategroups})";
            $groups = $DB->get_recordset_sql($query,$params);
            $groups = $this->array2str($groups);
        }
        
        
        // Get the gradebook users for this course and/or group
        $currentgroup = groups_get_course_group($COURSE, true);
        
        if (!$currentgroup) {      // To make some other functions work better later
            $currentgroup  = NULL;
        }
        
        // we are looking for all users with this role assigned in this context or higher
        $contextlist = get_related_contexts_string($context);
    
        list($esql, $params) = get_enrolled_sql($context, NULL, $currentgroup, true);
        $joins = array("FROM {user} u");
        $wheres = array();
    
        if ($isfrontpage) {
            $select = ($CFG->dbtype == 'mysql' || $CFG->dbtype == 'mysqli') ? "SELECT GROUP_CONCAT(u.id) ids" : "SELECT u.id";
            $joins[] = "JOIN ($esql) e ON e.id = u.id"; // everybody on the frontpage usually 
        } else {
            $select = ($CFG->dbtype == 'mysql' || $CFG->dbtype == 'mysqli') ? "SELECT GROUP_CONCAT(u.id) ids" : "SELECT u.id";
            $joins[] = "JOIN ($esql) e ON e.id = u.id"; // course enrolled users only
            $joins[] = "LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid)"; // not everybody accessed course yet
            $params['courseid'] = $COURSE->id;
        }
        
        // limit list to users with some role only
        $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid IN (:roleid) AND contextid $contextlist)";
        $params['roleid'] = $CFG->gradebookroles;
    
        $from = implode("\n", $joins);
        if ($wheres) {
            $where = "WHERE " . implode(" AND ", $wheres);
        } else {
            $where = "";
        }
        
        $userlist = $DB->get_recordset_sql("$select $from $where", $params);
        if ($CFG->dbtype == 'mysql' || $CFG->dbtype == 'mysqli') {
            foreach ($userlist AS $user) $gradebookusers = $user->ids;
        } else {
            foreach ($userlist AS $user) $gradebookusers[] = $user->id;
            $gradebookusers = $this->array2str($gradebookusers);
        }
        
        
        if ($gradebookusers != '') {
            $query_assignment = "SELECT c.id course_id 
                           , c.shortname course_name 
                           , 'assignment' type
                           , a_s.id sub_id 
                           , a.id mod_id 
                           , a.name mod_name 
                           , c_m.id cm_id 
                           , u.id user_id 
                           , u.lastname last_name 
                           , u.firstname first_name 
                           , a_s.timemodified time_submitted 
                        FROM {assignment_submissions} a_s 
                  INNER JOIN {assignment} a 
                          ON (a.id = a_s.assignment)
                  INNER JOIN {user} u 
                          ON (u.id = a_s.userid)
                  INNER JOIN {course} c 
                          ON (c.id = a.course)
                   LEFT JOIN {course_modules} c_m 
                          ON (c_m.instance = a.id  AND  c_m.module IN (SELECT m.id FROM {modules} m WHERE m.name='assignment')) 
                       WHERE a.grade > 0
                         AND a_s.timemodified > a_s.timemarked 
                         AND u.id IN ({$gradebookusers}) 
                    ";
            
            $query_data = "SELECT c.id course_id 
                           , c.shortname course_name 
                           , 'data' type
                           , dr.id sub_id 
                           , d.id mod_id 
                           , d.name mod_name 
                           , cm.id cm_id 
                           , u.id user_id 
                           , u.lastname last_name 
                           , u.firstname first_name 
                           , dr.timemodified time_submitted 
                        FROM {data_records} dr 
                  INNER JOIN {data} d 
                          ON (d.id = dr.dataid)
                  INNER JOIN {user} u 
                          ON (u.id = dr.userid)
                  INNER JOIN {course} c 
                          ON (c.id = d.course)
                   LEFT JOIN {course_modules} cm 
                          ON (cm.instance = d.id  AND  cm.module IN (SELECT m.id FROM {modules} m WHERE m.name='data')) 
                       WHERE d.assessed = 1
                         AND CONCAT(dr.id,'-',{$USER->id}) NOT IN (SELECT CONCAT(r.itemid,'-',r.userid) FROM {rating} r WHERE r.contextid IN (SELECT cx.id FROM {context} cx WHERE cx.contextlevel = 70 AND cx.instanceid = cm.id))
                         AND u.id IN ({$gradebookusers}) 
                    ";
            
            $query_forum = "SELECT c.id course_id 
                           , c.shortname course_name 
                           , 'forum' type
                           , fp.id sub_id 
                           , fd.id mod_id 
                           , f.name mod_name 
                           , c_m.id cm_id 
                           , u.id user_id 
                           , u.lastname last_name 
                           , u.firstname first_name 
                           , fp.modified time_submitted 
                        FROM {forum_posts} fp 
                  INNER JOIN {forum_discussions} fd 
                          ON (fd.id = fp.discussion)
                  INNER JOIN {forum} f 
                          ON (f.id = fd.forum)
                  INNER JOIN {user} u 
                          ON (u.id = fp.userid)
                  INNER JOIN {course} c 
                          ON (c.id = f.course)
                   LEFT JOIN {course_modules} c_m 
                          ON (c_m.instance = f.id  AND  c_m.module IN (SELECT m.id FROM {modules} m WHERE m.name='forum')) 
                       WHERE f.assessed = 1
                         AND CONCAT(fp.id,'-',{$USER->id}) NOT IN (SELECT CONCAT(r.itemid,'-',r.userid) FROM {rating} r WHERE r.contextid IN (SELECT cx.id FROM {context} cx WHERE cx.contextlevel = 70 AND cx.instanceid = c_m.id))
                         AND u.id IN ({$gradebookusers})
                    ";
            
            $query_glossary = "SELECT c.id course_id 
                           , c.shortname course_name 
                           , 'glossary' type
                           , ge.id sub_id 
                           , g.id mod_id 
                           , g.name mod_name 
                           , cm.id cm_id 
                           , u.id user_id 
                           , u.lastname last_name 
                           , u.firstname first_name 
                           , ge.timemodified time_submitted  
                        FROM {glossary_entries} ge
                  INNER JOIN {glossary} g ON (g.id = ge.glossaryid)
                  INNER JOIN {user} u 
                          ON (u.id = ge.userid)
                  INNER JOIN {course} c 
                          ON (c.id = g.course)
                   LEFT JOIN {course_modules} cm 
                          ON (cm.instance = g.id  AND  cm.module IN (SELECT m.id FROM {modules} m WHERE m.name='glossary')) 
                       WHERE g.assessed = 1 
                         AND CONCAT(ge.id,'-',{$USER->id}) NOT IN (SELECT CONCAT(r.itemid,'-',r.userid) FROM {rating} r WHERE r.contextid IN (SELECT cx.id FROM {context} cx WHERE cx.contextlevel = 70 AND cx.instanceid = cm.id)) 
                         AND u.id IN ({$gradebookusers}) 
                    ";
    /*        
            $query_lesson = "SELECT 
                    ";
    */        
            $query_quiz = "SELECT c.id course_id
                           , c.shortname course_name
                           , 'quiz' type
                           , qs.attemptid state_id 
                           , q.id mod_id
                           , q.name mod_name
                           , c_m.id cm_id 
                           , u.id user_id
                           , u.lastname last_name
                           , u.firstname first_name
                           , qa.timefinish time_submitted
                        FROM {quiz_attempts} qa
                  INNER JOIN {question_sessions} qs 
                          ON (qs.attemptid = qa.id) 
                  INNER JOIN {quiz} q 
                          ON (q.id = qa.quiz)  
                  INNER JOIN {quiz_question_instances} qqi 
                          ON (qqi.quiz = q.id  AND  qqi.question = qs.questionid) 
                  INNER JOIN {user} u 
                          ON (qa.userid = u.id) 
                  INNER JOIN {course} c 
                          ON (c.id = q.course) 
                   LEFT JOIN {course_modules} c_m 
                          ON (c_m.instance = q.id  AND  c_m.module IN (SELECT m.id FROM {modules} m WHERE m.name='quiz')) 
                       WHERE qa.timefinish != 0
                         AND qqi.grade > 0
                         AND qs.newgraded != qs.newest
                         AND u.id IN ({$gradebookusers}) 
                    ";
    /*        
            $query_workshop = "SELECT 
                    ";
    */        
            
            $query_groups = "AND (g.id IN (SELECT {groups}.id 
                            FROM {groups} 
                               , {groups_members} 
                           WHERE {groups}.id = {groups_members}.groupid 
                             AND {groups_members}.userid = u.id 
                             AND {groups}.id IN ({$groups}) 
                             ) 
                          OR g.id IS NULL)
                    ";
        }
        
        foreach ($supported_mods AS $mod => $permission) {
        
            // check if user has permission to grade this mod
            if (isset($count['grader_grouped'][$mod])  &&  isset($groups)) {
                $courselist = $grader[$mod]['grouped'];
                $ungraded_queries[] = ${'query_'.$mod}." AND c.id IN ({$courselist}) ".$query_groups;
            }
            
            if (isset($count['grader'][$mod])) {
                $courselist = $grader[$mod]['nongrouped'];
                if (isset(${'query_'.$mod})) $ungraded_queries[] = ${'query_'.$mod}." AND c.id IN ({$courselist}) ";
            }
        }
         
        
        if (count($count['grader']) && count($ungraded_queries) > 0) {
            
            $query = '('.implode(') UNION (',$ungraded_queries).') ORDER BY course_name, course_id, mod_name, cm_id LIMIT '.($maxitems+1);
            $rs = $DB->get_recordset_sql($query);
            
            $i = 1;
            foreach ($rs AS $result) {
                if ($i == ($maxitems+1)) {
                    $excess = true;
                    break;
                }
                $grade_me_arr[$result->course_id]['course_name'] = $result->course_name;
                $grade_me_arr[$result->course_id]['cm'][$result->cm_id]['mod_id'] = $result->mod_id;
                $grade_me_arr[$result->course_id]['cm'][$result->cm_id]['mod_name'] = $result->mod_name;
                $grade_me_arr[$result->course_id]['cm'][$result->cm_id]['type'] = $result->type;
                $grade_me_arr[$result->course_id]['cm'][$result->cm_id]['submission'][$result->sub_id]['user_id'] = $result->user_id;
                $grade_me_arr[$result->course_id]['cm'][$result->cm_id]['submission'][$result->sub_id]['first_name'] = $result->first_name;
                $grade_me_arr[$result->course_id]['cm'][$result->cm_id]['submission'][$result->sub_id]['last_name'] = $result->last_name;
                $grade_me_arr[$result->course_id]['cm'][$result->cm_id]['submission'][$result->sub_id]['time_submitted'] = $result->time_submitted;
                $i++;
            }
            
            if ($excess) $this->content->text .= '<div class="excess">'.get_string('excess','block_grade_me',array()).'</div>'."\n";
            
            // get image path
            $gradeImg = $CFG->wwwroot.'/blocks/grade_me/pix/check_mark.png';
            
            $last = array();
            
            if (count($grade_me_arr)) {
                $this->content->text .= '<dl>'."\n";
                foreach ($grade_me_arr AS $course_id => $course_id_arr) {
                    
                    $this->content->text .= '<dt class="cmod"><a href="'.$CFG->wwwroot.'/grade/report/index.php?id='.$course_id.'" title="'.get_string('link_gradebook_icon','block_grade_me',array('course_name' => $course_id_arr['course_name'])).'">'.$OUTPUT->pix_icon('i/grades',get_string('alt_gradebook','block_grade_me',array('course_name' => $course_id_arr['course_name'])),null,array('class' => 'gm_icon')).'</a> <a href="'.$CFG->wwwroot.'/course/view.php?id='.$course_id.'" title="'.get_string('link_gradebook','block_grade_me',array('course_name' => $course_id_arr['course_name'])).'">'.$course_id_arr['course_name'].'</a></dt>'."\n";
                    
                    foreach ($course_id_arr['cm'] AS $cm_id => $cm_id_arr) {
                        
                        if ($cm_id_arr['type'] == 'assignment') $link = '/mod/'.$cm_id_arr['type'].'/submissions.php?id='.$cm_id;
                        elseif ($cm_id_arr['type'] == 'data') $link = '/mod/'.$cm_id_arr['type'].'/view.php?id='.$cm_id;
                        elseif ($cm_id_arr['type'] == 'forum') $link = '/mod/'.$cm_id_arr['type'].'/view.php?id='.$cm_id;
                        elseif ($cm_id_arr['type'] == 'glossary') $link = '/mod/'.$cm_id_arr['type'].'/view.php?id='.$cm_id;
                        elseif ($cm_id_arr['type'] == 'quiz') $link = '/mod/'.$cm_id_arr['type'].'/report.php?id='.$cm_id;
                        
                        $modlink = '/mod/'.$cm_id_arr['type'].'/view.php?id='.$cm_id;
                        
                        $this->content->text .= '<dd id="cmid'.$cm_id.'" class="module">'."\n";  //open module
                        $module_img = ($link) ? '<a href="'.$CFG->wwwroot.$link.'" title="'.get_string('link_mod_img','block_grade_me',array('mod_name' => $cm_id_arr['mod_name'])).'">'.$OUTPUT->pix_icon('icon',get_string('link_mod_img','block_grade_me',array('mod_name' => $cm_id_arr['mod_name'])),$cm_id_arr['type'],array('class' => 'gm_icon')).'</a>' : $OUTPUT->pix_icon('icon',get_string('link_mod_img','block_grade_me',array('mod_name' => $cm_id_arr['mod_name'])),$cm_id_arr['type'],array('class' => 'gm_icon'));
                        if ($link) $this->content->text .= '<div class="toggle" onclick="$(\'dd#cmid'.$cm_id.' > div.toggle\').toggleClass(\'open\');$(\'dd#cmid'.$cm_id.' > ul\').toggleClass(\'block_grade_me_hide\');"></div>'."\n";
                        unset($link);
                        
                        $this->content->text .= $module_img.'<a href="'.$CFG->wwwroot.$modlink.'" title="'.get_string('link_mod','block_grade_me',array('mod_name' => $cm_id_arr['mod_name'])).'">'.$cm_id_arr['mod_name'].'</a> ('.count($cm_id_arr['submission']).')'."\n";
                        $this->content->text .= '<ul class="block_grade_me_hide">'."\n";
                        
                        foreach ($cm_id_arr['submission'] AS $sub_id => $sub_id_arr) {
                            
                            if ($cm_id_arr['type'] == 'assignment') $submission_link = '/mod/'.$cm_id_arr['type'].'/submissions.php?id='.$cm_id.'&amp;userid='.$sub_id_arr['user_id'].'&amp;mode=single&amp;filter=0&amp;offset=0';
                            elseif ($cm_id_arr['type'] == 'data') $submission_link = '/mod/'.$cm_id_arr['type'].'/view.php?d='.$sub_id.'&amp;mode=single';
                            elseif ($cm_id_arr['type'] == 'forum') $submission_link = '/mod/'.$cm_id_arr['type'].'/discuss.php?d='.$cm_id_arr['mod_id'].'#p'.$sub_id;
                            elseif ($cm_id_arr['type'] == 'glossary') $submission_link = '/mod/'.$cm_id_arr['type'].'/view.php?id='.$cm_id.'#postrating'.$sub_id;
                            elseif ($cm_id_arr['type'] == 'quiz') $submission_link = '/mod/'.$cm_id_arr['type'].'/report.php?q='.$cm_id_arr['mod_id'].'&amp;mode=grading';
                            
                            $this->content->text .= '<li class="gradable">';  // open gradable
                            $this->content->text .= '<a href="'.$CFG->wwwroot.$submission_link.'" title="'.get_string('link_grade_img','block_grade_me',array()).'"><img src="'.$gradeImg.'" class="gm_icon" alt="'.get_string('alt_mark','block_grade_me',array()).'" /></a>';  // grade icon
                            unset($submission_link);  // we don't need this anymore
                            $this->content->text .= '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$sub_id_arr['user_id'].'&amp;course='.$course_id.'" title="'.get_string('link_user_profile','block_grade_me',array('first_name' => $sub_id_arr['first_name'])).'">'.$sub_id_arr['first_name'].' '.$sub_id_arr['last_name'].'</a>';  // user name and profile link
                            $this->content->text .= '<br />'.userdate($sub_id_arr['time_submitted'],$date_time_string);  // output submission date
                            $this->content->text .= '</li>'."\n";  // end gradable
                        }
                        
                        $this->content->text .= '</ul>'."\n";
                        $this->content->text .= '</dd>'."\n";  // close module 
                        
                    }
                }
                $this->content->text .= '</dl>'."\n";
            }
            
            if (!$this->content->text  &&  (count($count['grader']))) {
                $this->content->text .= '<div class="empty">'.$OUTPUT->pix_icon('s/smiley',get_string('alt_smiley','block_grade_me',array())).' '.get_string('nothing','block_grade_me',array()).'</div>'."\n";
            }
            
            $this->content->text .= '';
        }
        
        return $this->content;
    }
}
