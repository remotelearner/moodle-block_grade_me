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
 * PHPUnit data generator tests
 *
 * @package    block_grade_me
 * @category   phpunit
 * @copyright  2013 Logan Reynolds {@link http://www.remote-learner.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot.'/blocks/moodleblock.class.php');
require_once($CFG->dirroot.'/blocks/grade_me/lib.php');
require_once($CFG->dirroot.'/blocks/grade_me/block_grade_me.php');
require_once($CFG->dirroot.'/blocks/grade_me/plugins/assign/assign_plugin.php');
require_once($CFG->dirroot.'/blocks/grade_me/plugins/assignment/assignment_plugin.php');
require_once($CFG->dirroot.'/blocks/grade_me/plugins/data/data_plugin.php');
require_once($CFG->dirroot.'/blocks/grade_me/plugins/forum/forum_plugin.php');
require_once($CFG->dirroot.'/blocks/grade_me/plugins/glossary/glossary_plugin.php');
require_once($CFG->dirroot.'/blocks/grade_me/plugins/quiz/quiz_plugin.php');

defined('MOODLE_INTERNAL') || die();

class block_grade_me_testcase extends advanced_testcase {

    /**
     * Load the testing dataset. Meant to be used by any tests that require the testing dataset.
     *
     * @param string $file The name of the data file to load
     * @param string $type The name of the module we are testing
     * @return array An array containing an array of user objects and an array of course objects
     */
    protected function create_grade_me_data($file) {
        // Read the datafile and get the table names.
        $dataset = $this->createXMLDataSet(__DIR__.'/fixtures/'.$file);
        $names = array_flip($dataset->getTableNames());

        // Generate Data
        $generator = $this->getDataGenerator();
        $users = array($generator->create_user(), $generator->create_user());
        $courses = array($generator->create_course());
        $plugins = array();
        $excludes = array();

        $gradeables = array('assign', 'assignment', 'forum', 'glossary', 'quiz');
        foreach ($gradeables as $gradeable) {
            if (array_key_exists($gradeable, $names)) {
                $pgen = $generator->get_plugin_generator("mod_{$gradeable}");
                $table = $dataset->getTable($gradeable);
                $rows = $table->getRowCount();
                $items = array();
                for ($row = 0; $row < $rows; $row += 1) {
                    $fields = $table->getRow($row);
                    unset($fields['id']);
                    $fields['course'] = $courses[$fields['course']]->id;
                    $instance = $pgen->create_instance($fields);
                    $context = context_module::instance($instance->cmid);
                    $plugins[] = (object) array('id' => $instance->id, 'cmid' => $instance->cmid, 'contextid' => $context->id);
                }
            }
            $excludes[] = $gradeable;
        }

        // Known overrides (compact form)
        $overrides = array(
            'assignment'   => array(
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => array('assign_grades', 'assign_submission', 'assignment_submissions'),
            ),
            'contextid'    => array(
                'values' => 'plugins',
                'param'  => 'contextid',
                'tables' => array('rating'),
            ),
            'course'       => array(
                'values' => 'courses',
                'param'  => 'id',
                'tables' => array(
                        'assign', 'assignment', 'course_modules', 'forum', 'forum_discussions',
                        'glossary', 'quiz',
                ),
            ),
            'courseid'     => array(
                'values' => 'courses',
                'param'  => 'id',
                'tables' => array('block_grade_me', 'grade_items'),
            ),
            'coursemoduleid'   => array(
                'values' => 'plugins',
                'param'  => 'cmid',
                'tables' => array('block_grade_me'),
            ),
            'coursename'   => array(
                'values' => 'courses',
                'param'  => 'fullname',
                'tables' => array('block_grade_me'),
            ),
            'forum'        => array(
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => array('forum_discussions'),
            ),
            'glossaryid'        => array(
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => array('glossary_entries'),
            ),
            'iteminstance' => array(
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => array('block_grade_me', 'grade_items'),
            ),
            'quiz' => array(
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => array('quiz_attempts'),
            ),
            'userid'       => array(
                'values' => 'users',
                'param'  => 'id',
                'tables' => array(
                    'assign_grades', 'assign_submission', 'assignment_submissions', 'forum_posts',
                    'forum_discussions', 'glossary_entries', 'grade_grades', 'question_attempt_steps',
                    'quiz_attempts',
                ),
            ),
        );

        // Generate a table oriented list of overrides.
        $tables = array();
        foreach ($overrides as $field => $override) {
            foreach ($override['tables'] as $tablename) {
                // Skip tables that aren't in the dataset
                if (array_key_exists($tablename, $names)) {
                    if (!array_key_exists($tablename, $tables)) {
                        $tables[$tablename] = array($field => array());
                    }
                    $tables[$tablename][$field][] = array('list' => $override['values'], 'field' => $override['param']);
                }
            }
        }

        // Perform the overrides
        foreach ($tables as $tablename => $translations) {
            $table = $dataset->getTable($tablename);
            $rows = $table->getRowCount();
            foreach ($translations as $column => $values) {
                foreach ($values as $value) {
                    $list = $value['list'];
                    $field = $value['field'];
                    for ($row = 0; $row < $rows; $row += 1) {
                        $index = $table->getValue($row, $column);
                        $table->setValue($row, $column, ${$list}[$index]->$field);
                    }
                }
            }
        }

        // Load the data
        $filtered = new PHPUnit_Extensions_Database_DataSet_DataSetFilter($dataset);
        $filtered->addExcludeTables($excludes);
        $this->loadDataSet($filtered);

        // Return the generated users and courses because the tests often need them for result calculations
        return array($users, $courses, $plugins);
    }

    /**
     * Provide input data to the parameters of the test_censusreport_null_grade_check() method.
     *
     * @TODO See if this can be merged with provider_single_user
     *
     * Test data is composed of:
     *     The plugin to be tested
     *     Regular expressions to matched against the output
     *     A list of users
     *     A list of courses
     *
     * @return array An array containing the test data
     */
    public function provider_get_content_multiple_user() {
        $data = array();

        // New assign test
        $plugin = 'assign';
        $matches = array(
            1 => '/Go to assign/',
            2 => '|mod/assign/view.php|',
            3 => '/action=grade&rownum=0&useridlistid=/',
            4 => '/action=grade&rownum=1&useridlistid=/',
            5 => '/testassignment3/',
            6 => '/testassignment4/'
        );
        $data['assign'] = array($plugin, $matches);

        // Legacy assignment test
        $plugin = 'assignment';
        $matches = array(
            1 => '/Go to assignment/',
            2 => '|mod/assignment/submissions.php|',
            3 => '/userid=[user0]&amp;mode=single/',
            4 => '/userid=[user1]&amp;mode=single/',
            5 => '/testassignment5/',
            6 => '/testassignment6/',
        );
        $data['assignment'] = array($plugin, $matches);

        return $data;
    }

    /**
     * Provide input data to the parameters of the test_block_grade_me_get_content_single_user() method.
     *
     * Test data is composed of:
     *     The plugin to be tested
     *     Regular expressions to matched against the output
     *     A list of users
     *     A list of courses
     *
     * @return array An array containing the test data
     */
    public function provider_get_content_single_user() {
        $data = array();

        // New assign test
        $plugin = 'assign';
        $matches = array(
            1 => '/Go to assign/',
            2 => '|mod/assign/view.php|',
            3 => '/action=grade&rownum=0&useridlistid=/',
            5 => '/testassignment3/',
            6 => '/testassignment4/',
        );
        $data['assign'] = array($plugin, $matches);

        // Legacy assignment test
        $plugin = 'assignment';
        $matches = array(
            1 => '/Go to assignment/',
            2 => '|mod/assignment/submissions.php|',
            3 => '/userid=[user0]&amp;mode=single/',
            5 => '/testassignment5/',
            6 => '/testassignment6/',
        );
        $data['assignment'] = array($plugin, $matches);

        return $data;
    }

    /**
     * Data provider for the forum plugin.
     *
     * @TODO Make this data provider less useless.
     *
     * @return array Forum items
     */
    public function provider_query_forum() {
        // Represents forum items that are ready for grading. Forum items that have already been graded are not included.
        $forumitem1 = array(
            'courseid'            => 0,
            'coursename'          => '',
            'itemmodule'          => 'forum',
            'iteminstance'        => 0,
            'itemname'            => 'forumitem1',
            'coursemoduleid'      => 0,
            'itemsortorder'       => 0,
            'submissionid'        => 1,
            'userid'              => 0,
            'timesubmitted'       => 0,
            'forum_discussion_id' => 1
        );

        $forumitem2 = array(
            'courseid'            => 0,
            'coursename'          => '',
            'itemmodule'          => 'forum',
            'iteminstance'        => 0,
            'itemname'            => 'forumitem1',
            'coursemoduleid'      => 0,
            'itemsortorder'       => 0,
            'submissionid'        => 2,
            'userid'              => 0,
            'timesubmitted'       => 0,
            'forum_discussion_id' => 2
        );

        $data = array(array(array($forumitem1, $forumitem2)));

        return $data;
    }

    /**
     * Data provider for the testing the quiz plugin.
     *
     * @return array Glossary entries
     */
    public function provider_query_glossary() {
        $datafile = 'glossary.xml';
        // Represents entries that are finished and ready to be graded.
        $entries = array();
        $entries[0] = array(
            'courseid'       => 0,
            'coursename'     => '0',
            'itemmodule'     => 'glossary',
            'iteminstance'   => 0,
            'itemname'       => 'glossaryitem1',
            'coursemoduleid' => 0,
            'itemsortorder'  => 0,
            'userid'         => 0,
            'timesubmitted'  => 1424354368,
            'submissionid'   => 1,
        );

        $entries[1] = array(
            'courseid'       => 0,
            'coursename'     => '0',
            'itemmodule'     => 'glossary',
            'iteminstance'   => 1,
            'itemname'       => 'glossaryitem2',
            'coursemoduleid' => 1,
            'itemsortorder'  => 0,
            'userid'         => 0,
            'timesubmitted'  => 1424354369,
            'submissionid'   => 2,
        );

        $entries[2] = array(
            'courseid'       => 0,
            'coursename'     => '0',
            'itemmodule'     => 'glossary',
            'iteminstance'   => 2,
            'itemname'       => 'glossaryitem3',
            'coursemoduleid' => 2,
            'itemsortorder'  => 0,
            'userid'         => 0,
            'timesubmitted'  => 1424354370,
            'submissionid'   => 3,
        );

        $data = array(
            'test1' => array($datafile, $entries)
        );

        return $data;
    }

    /**
     * Data provider for the testing the quiz plugin.
     *
     * @return array Quiz questions
     */
    public function provider_query_quiz() {
        // Represents questions that are finished and ready to be graded.
        // In progress questions or questions that are already graded are not included.
        $items = array();
        $items[0] = array(
            'courseid'       => 0,
            'coursename'     => '',
            'itemmodule'     => 'quiz',
            'iteminstance'   => 1,
            'itemname'       => 'quizitem2',
            'coursemoduleid' => 1,
            'itemsortorder'  => 0,
            'step_id'        => 4,
            'userid'         => 0,
            'timesubmitted'  => 0,
            'submissionid'   => 2,
            'sequencenumber' => 2
        );

        $items[1] = array(
            'courseid'       => 0,
            'coursename'     => '',
            'itemmodule'     => 'quiz',
            'iteminstance'   => 3,
            'itemname'       => 'quizitem4',
            'coursemoduleid' => 3,
            'itemsortorder'  => 0,
            'step_id'        => 11,
            'userid'         => 0,
            'timesubmitted'  => 0,
            'submissionid'   => 4,
            'sequencenumber' => 2
        );

        $items[2] = array(
            'courseid'       => 0,
            'coursename'     => '',
            'itemmodule'     => 'quiz',
            'iteminstance'   => 0,
            'itemname'       => 'Quiz #1',
            'coursemoduleid' => 0,
            'itemsortorder'  => 0,
            'step_id'        => 9,
            'userid'         => 0,
            'timesubmitted'  => 0,
            'submissionid'   => 1,
            'sequencenumber' => 2
        );

        $data = array(
            'simple'      => array('quiz1.xml', array($items[0], $items[1])),
            'complexquiz' => array('quiz2.xml', array($items[2])),
        );

        return $data;
    }

    /**
     * Confirm that the block will include the relevant settings.php file
     * for Moodle 2.4.
     */
    public function test_global_configuration_load() {
        $this->resetAfterTest(true);
        $block_inst = block_instance('grade_me');
        $this->assertEquals(true, $block_inst->has_config());
    }

    /**
     * Ensure that we can load our test dataset into the current DB.
     */
    public function test_load_db() {
        $this->resetAfterTest(true);
        $this->create_grade_me_data('block_grade_me.xml');
    }

    /**
     * Test the function block_grade_me_query_assign.
     *
     * @depends test_load_db
     */
    public function test_query_assign() {
        global $DB;

        $this->resetAfterTest(true);
        list($users, $courses, $plugins) = $this->create_grade_me_data('block_grade_me.xml');

        // Partial query return from block_grade_me_query_assign.
        list($sql, $insqlparams) = block_grade_me_query_assign(array($users[0]->id));
        // Build full query.
        $sql = "SELECT a.id, bgm.courseid $sql AND bgm.courseid = {$courses[0]->id} AND bgm.itemmodule = 'assign'";

        $rec = new stdClass();
        $rec->id = $plugins[2]->id;
        $rec->courseid = $courses[0]->id;
        $rec->submissionid = '2';
        $rec->userid = $users[0]->id;
        $rec->timesubmitted = '2';

        $rec2 = new stdClass();
        $rec2->id = $plugins[3]->id;
        $rec2->courseid = $courses[0]->id;
        $rec2->submissionid = '3';
        $rec2->userid = $users[0]->id;
        $rec2->timesubmitted = '3';

        // Tests resubmission
        $rec3 = new stdClass();
        $rec3->id = $plugins[4]->id;
        $rec3->courseid = $courses[0]->id;
        $rec3->submissionid = '7';
        $rec3->userid = $users[0]->id;
        $rec3->timesubmitted = '6';


        $expected = array($rec->id => $rec, $rec2->id => $rec2, $rec3->id => $rec3);
        $actual = $DB->get_records_sql($sql, $insqlparams);
        $this->assertEquals($expected, $actual);
        $this->assertFalse(block_grade_me_query_assign(array()));
    }

    /**
     * Test the block_grade_me_query_prefix function
     */
    public function test_query_prefix() {
        $expected = "SELECT bgm.courseid, bgm.coursename, bgm.itemmodule, bgm.iteminstance, bgm.itemname, bgm.coursemoduleid, bgm.itemsortorder";
        $this->assertEquals($expected, block_grade_me_query_prefix());
    }

    /**
     * Test the block_grade_me_query_suffix function
     */
    public function test_query_suffix() {
        $expected = " AND bgm.courseid = ?
 AND bgm.itemmodule = 'assign'";
        $this->assertEquals($expected, block_grade_me_query_suffix('assign'));
    }

    /**
     * Dataprovider for testing the cron
     *
     * @return array Grade item data
     */
    public function provider_cron() {
        $item2 = new StdClass();
        $item2->id = 2;
        $item2->itemname = 'testassignment2';
        $item2->itemtype = 'mod';
        $item2->itemmodule = 'assign';

        $item3 = new StdClass();
        $item3->id = 3;
        $item3->itemname = 'testassignment3';
        $item3->itemtype = 'mod';
        $item3->itemmodule = 'assign';

        $item4 = new StdClass();
        $item4->id = 4;
        $item4->itemname = 'testassignment4';
        $item4->itemtype = 'mod';
        $item4->itemmodule = 'assign';

        $item5 = new StdClass();
        $item5->id = 5;
        $item5->itemname = 'testassignment6';
        $item5->itemtype = 'mod';
        $item5->itemmodule = 'assignment';

        // Represents updated record.
        $item6 = new StdClass();
        $item6->id = 6;
        $item6->itemname = 'testassignment7_UPDATED';
        $item6->itemtype = 'mod';
        $item6->itemmodule = 'assignment';

        $item7 = new StdClass();
        $item7->id = 7;
        $item7->itemname = 'testassignment5';
        $item7->itemtype = 'mod';
        $item7->itemmodule = 'assign';

        $item8 = new StdClass();
        $item8->id = 8;
        $item8->itemname = 'test_forum';
        $item8->itemtype = 'mod';
        $item8->itemmodule = 'forum';

        $data = array(
                array(
                    array(
                        '2' => $item2,
                        '3' => $item3,
                        '4' => $item4,
                        '5' => $item5,
                        '6' => $item6,
                        '7' => $item7,
                        '8' => $item8
                    )
                )
        );

        return $data;

    }

    /**
     * Test the cron
     *
     * @param array $expected The expected data
     * @dataProvider provider_cron
     * @depends test_load_db
     */
    public function test_cron($expected) {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        $this->create_grade_me_data('block_grade_me.xml');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course();

        $grademe = new block_grade_me();
        $grademe->cron();
        $this->expectOutputRegex('/Updated block_grade_me cache in/');
        $actual = $DB->get_records('block_grade_me', array(), '', 'id, itemname, itemtype, itemmodule');
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test the quiz plugin where a list of questions not yet graded is returned.
     *
     * @param string $datafile The database file to load for the test
     * @param array $expected The expected results
     * @dataProvider provider_query_quiz
     */
    public function test_query_quiz($datafile, $expected) {
        global $DB;

        $this->resetAfterTest(true);
        list($users, $courses, $plugins) = $this->create_grade_me_data($datafile);

        list($sql, $params) = block_grade_me_query_quiz(array($users[0]->id));
        $sql = block_grade_me_query_prefix().$sql.block_grade_me_query_suffix('quiz');

        $actual = array();
        $result = $DB->get_recordset_sql($sql, array($params[0], $courses[0]->id));
        foreach ($result as $rec) {
            $actual[] = (array)$rec;
        }

        // Set proper values for the results
        foreach ($expected as $key => $row) {
            $row['coursemoduleid'] = $plugins[$row['coursemoduleid']]->cmid;
            $row['coursename'] = $courses[$row['courseid']]->fullname;
            $row['courseid'] = $courses[$row['courseid']]->id;
            $row['iteminstance'] = $plugins[$row['iteminstance']]->id;
            $row['userid'] = $users[$row['userid']]->id;
            $expected[$key] = $row;
        }

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test the forum plugin where a list of forum activites not yet graded is returned.
     *
     * @dataProvider provider_query_forum
     * @param array $expected The expected results
     */
    public function test_query_forum($expected) {
        global $DB;

        $this->resetAfterTest(true);
        list($users, $courses, $plugins) = $this->create_grade_me_data('forum.xml');

        list($sql, $params) = block_grade_me_query_forum(array($users[0]->id));
        $sql = block_grade_me_query_prefix().$sql.block_grade_me_query_suffix('forum');

        $actual = array();
        $result = $DB->get_recordset_sql($sql, array($params[0], $courses[0]->id));
        foreach ($result as $rec) {
            $actual[] = (array)$rec;
        }

        // Set proper values for the results
        foreach ($expected as $key => $row) {
            $row['coursemoduleid'] = $plugins[$row['coursemoduleid']]->cmid;
            $row['coursename'] = $courses[$row['courseid']]->fullname;
            $row['courseid'] = $courses[$row['courseid']]->id;
            $row['iteminstance'] = $plugins[$row['iteminstance']]->id;
            $row['userid'] = $users[$row['userid']]->id;
            $expected[$key] = $row;
        }

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test the block_grade_me_query_glossary function
     *
     * @param string $datafile The database file to load for the test
     * @param array $expected The expected results
     * @dataProvider provider_query_glossary
     */
    public function test_query_glossary($datafile, $expected) {
        global $USER, $DB;

        $this->resetAfterTest(true);
        list($users, $courses, $plugins) = $this->create_grade_me_data($datafile);

        list($sql, $params) = block_grade_me_query_glossary(array($users[0]->id));
        $sql = block_grade_me_query_prefix().$sql.block_grade_me_query_suffix('glossary');

        $actual = array();
        $result = $DB->get_recordset_sql($sql, array($params[0], $courses[0]->id));
        foreach ($result as $rec) {
            $actual[] = (array)$rec;
        }

        // Set proper values for the results
        foreach ($expected as $key => $row) {
            $row['coursemoduleid'] = $plugins[$row['coursemoduleid']]->cmid;
            $row['coursename'] = $courses[$row['courseid']]->fullname;
            $row['courseid'] = $courses[$row['courseid']]->id;
            $row['iteminstance'] = $plugins[$row['iteminstance']]->id;
            $row['userid'] = $users[$row['userid']]->id;
            $expected[$key] = $row;
        }

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test the block_grade_me_query_data function
     */
    public function test_query_data() {
        global $USER, $DB;

        $concatid = $DB->sql_concat('dr.id', "'-'", $USER->id);
        $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');

        $expected = ", dr.id submissionid, dr.userid, dr.timemodified timesubmitted
        FROM {data_records} dr
        JOIN {data} d ON d.id = dr.dataid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = d.course AND bgm.iteminstance = d.id
       WHERE dr.userid IN (?,?)
             AND d.assessed = 1
             AND $concatid NOT IN (
             SELECT $concatitem
               FROM {rating} r
              WHERE r.contextid IN (
                    SELECT cx.id
                      FROM {context} cx
                     WHERE cx.contextlevel = 70
                           AND cx.instanceid = bgm.coursemoduleid
                    )
             )";

        list($sql, $params) = block_grade_me_query_data(array(2, 3));
        $this->assertEquals($expected, $sql);
        $this->assertEquals(array(2, 3), $params);
        $this->assertFalse(block_grade_me_query_data(array()));
    }

    /**
     * Test the block_grade_me_query_assignment function
     */
    public function test_query_assignment() {
        $expected = ", asgn_sub.id submissionid, asgn_sub.userid, asgn_sub.timemodified timesubmitted
        FROM {assignment_submissions} asgn_sub
        JOIN {assignment} a ON a.id = asgn_sub.assignment
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = a.course AND bgm.iteminstance = a.id
       WHERE asgn_sub.userid IN (?,?)
             AND a.grade > 0
             AND asgn_sub.timemarked < asgn_sub.timemodified";

        list($sql, $params) = block_grade_me_query_assignment(array(2, 3));
        $this->assertEquals($expected, $sql);
        $this->assertEquals(array(2, 3), $params);
        $this->assertFalse(block_grade_me_query_assignment(array()));
    }

    /**
     * Test the function get_content for one user in the gradebook for a course.
     * Check that urls returned are what they should be
     *
     * @param string $plugin         The name of the plugin being tested
     * @param array  $expectedvalues An array of values that should be found in the grade_me block output
     * @dataProvider provider_get_content_single_user
     * @depends test_load_db
     */
    public function test_get_content_single_user($plugin, $expectedvalues) {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);
        list($users, $courses) = $this->create_grade_me_data('block_grade_me.xml');

        // Make sure that the plugin being tested has been enabled
        if (!$CFG->{'block_grade_me_enable'.$plugin} == true) {
            set_config('block_grade_me_enable'.$plugin, true);
        }

        if (!$CFG->block_grade_me_enableadminviewall) {
            set_config('block_grade_me_enableadminviewall', true);
        }

        $this->setUser($users[0]);
        $this->setAdminUser($users[1]);
        $course = $this->getDataGenerator()->create_course();

        // Set up gradebook role
        $context = context_course::instance($courses[0]->id);
        $roleid = create_role('role', 'role', 'grade me block');
        set_role_contextlevels($roleid, array(CONTEXT_COURSE));
        role_assign($roleid, $users[0]->id, $context->id);
        set_config('gradebookroles', $roleid);

        // Create a manual enrolment record.
        $manual_enrol_data['enrol'] = 'manual';
        $manual_enrol_data['status'] = 0;
        $manual_enrol_data['courseid'] = 2;
        $enrolid = $DB->insert_record('enrol', $manual_enrol_data);

        // Create the user enrolment record.
        $DB->insert_record('user_enrolments', (object)array(
            'status' => 0,
            'enrolid' => $enrolid,
            'userid' => $users[0]->id
        ));

        $grademe = new block_grade_me();
        $content = $grademe->get_content();

        foreach ($expectedvalues as $expected) {
            $match = str_replace('[user0]', $users[0]->id, $expected);
            $this->assertRegExp($match, $content->text);
        }
    }

    /**
     * Test the function get_content.
     * Check that urls returned are what they should be
     *
     * @TODO See if this plugin can be merged with test_block_grade_me_get_content_single_user
     *
     * @param string $plugin         The name of the plugin being tested
     * @param array  $expectedvalues An array of values that should be found in the grade_me block output
     * @dataProvider provider_get_content_multiple_user
     * @depends test_load_db
     */
    public function test_get_content_multiple_user($plugin, $expectedvalues) {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);
        list($users, $courses) = $this->create_grade_me_data('block_grade_me.xml');

        // Make sure that the plugin being tested has been enabled
        if (!$CFG->{'block_grade_me_enable'.$plugin} == true) {
            set_config('block_grade_me_enable'.$plugin, true);
        }

        if (!$CFG->block_grade_me_enableadminviewall) {
            set_config('block_grade_me_enableadminviewall', true);
        }

        // When testing with multiple users
        // Need multiple gradebookroles and timemodified needs to be different on submission
        $this->setUser($users[0]);
        $adminuser = $this->getDataGenerator()->create_user();
        $this->setAdminUser($adminuser);

        // Set up gradebook roles
        $context = context_course::instance($courses[0]->id);
        $roleid = create_role('role', 'role', 'grade me block');
        $roleid2 = create_role('role2', 'role2', 'grade me block');
        set_role_contextlevels($roleid, array(CONTEXT_COURSE));
        role_assign($roleid, $users[0]->id, $context->id);
        role_assign($roleid2, $users[1]->id, $context->id);
        set_config('gradebookroles', "$roleid, $roleid2");

        // Create a manual enrolment record.
        $manual_enrol_data['enrol'] = 'manual';
        $manual_enrol_data['status'] = 0;
        $manual_enrol_data['courseid'] = 2;
        $enrolid = $DB->insert_record('enrol', $manual_enrol_data);

        // Create the user enrolment record.
        $DB->insert_record('user_enrolments', (object)array(
            'status' => 0,
            'enrolid' => $enrolid,
            'userid' => $users[0]->id
        ));
        $DB->insert_record('user_enrolments', (object)array(
            'status' => 0,
            'enrolid' => $enrolid,
            'userid' => $users[1]->id
        ));

        $grademe = new block_grade_me();
        $content = $grademe->get_content();

        foreach ($expectedvalues as $expected) {
            $match = str_replace('[user0]', $users[0]->id, $expected);
            $match = str_replace('[user1]', $users[1]->id, $match);
            $this->assertRegExp($match, $content->text);
        }
    }

    /**
     * Test that the forum plugin uses the correct ID link to a forum discussion.
     *
     * @depends test_load_db
     */
    public function test_tree_uses_correct_forum_discussion_id() {
        global $DB;

        $this->resetAfterTest(true);
        list($users, $courses, $plugins) = $this->create_grade_me_data('block_grade_me.xml');

        list($sql, $params) = block_grade_me_query_forum(array($users[0]->id));
        $sql = block_grade_me_query_prefix().$sql.block_grade_me_query_suffix('forum');
        $result = $DB->get_recordset_sql($sql, array($params[0], 'courseid' => $courses[0]->id));
        $gradeables = array();
        foreach ($result as $rec) {
            $gradeables = block_grade_me_array($gradeables, $rec);
        }

        $this->assertFalse(empty($gradeables), 'Expected results not found.');
        $actual = block_grade_me_tree($gradeables);
        $this->assertRegExp('/mod\/forum\/discuss.php\?d=100\#p1/', $actual);
    }
}
