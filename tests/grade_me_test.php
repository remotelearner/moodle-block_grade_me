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
     * Confirm that the block will include the relevant settings.php file
     * for Moodle 2.4.
     */
    public function test_global_configuration_load() {
        $block_inst = block_instance('grade_me');
        $this->assertEquals(true, $block_inst->has_config());
    }

    /**
     * Create the grade_me test data.
     */
    public function create_grade_me_data() {
        global $CFG, $DB;

        $data = array(
                array(
                    'itemid' => 2,
                    'itemname' => 'assignment',
                    'itemtype' => 'mod',
                    'itemmodule' => 'assign',
                    'iteminstance' => 1,
                    'courseid' => 2
                ),
                array(
                    'itemid' => 3,
                    'itemname' => 'assignment2',
                    'itemtype' => 'mod',
                    'itemmodule' => 'assign',
                    'iteminstance' => 2,
                    'courseid' => 2
                )
        );

        foreach ($data as $rec) {
            // Inserting records into block_grade_me through CSV or insert_record results in "unknown error fetching inserted id".
            $sql = 'INSERT INTO {block_grade_me}(itemid, itemname, itemtype, itemmodule, iteminstance, courseid) VALUES(?, ?, ?, ?, ?, ?)';
            $params = array($rec['itemid'], $rec['itemname'], $rec['itemtype'], $rec['itemmodule'], $rec['iteminstance'], $rec['courseid']);
            $DB->execute($sql, $params);
        }

        $basedir = "$CFG->dirroot/blocks/grade_me/tests/fixtures";
        $files = array(
            'assign' => "$basedir/assign.csv",
            'assign_grades' => "$basedir/assign_grades.csv",
            'assign_submission' => "$basedir/assign_submission.csv",
            'grade_items' => "$basedir/grade_items.csv",
            'course_modules' => "$basedir/course_modules.csv"
        );

        $this->loadDataSet($this->createCsvDataSet($files));
    }

    /**
     * Test the function block_grade_me_query_assign.
     */
    public function test_block_grade_me_query_assign() {
        global $DB;

        $this->resetAfterTest(true);
        $this->create_grade_me_data();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course();

        // Partial query return from block_grade_me_query_assign.
        list($sql, $insqlparams) = block_grade_me_query_assign(array($user->id));
        // Build full query.
        $sql = "SELECT a.id, bgm.courseid $sql AND bgm.courseid = {$course->id} AND bgm.itemmodule = 'assign'";

        $rec = new stdClass();
        $rec->id = 2;
        $rec->courseid = $course->id;
        $rec->submissionid = 2;
        $rec->userid = $user->id;
        $rec->timesubmitted = '1';

        $expected = array('2' => $rec);
        $actual = $DB->get_records_sql($sql, $insqlparams);
        $this->assertEquals($expected, $actual);
        $this->assertFalse(block_grade_me_query_assign(array()));
    }

    /**
     * Test the block_grade_me_query_prefix function
     */
    public function test_block_grade_me_query_prefix() {
        $expected = "SELECT bgm.courseid, bgm.coursename, bgm.itemmodule, bgm.iteminstance, bgm.itemname, bgm.coursemoduleid, bgm.itemsortorder";
        $this->assertEquals($expected, block_grade_me_query_prefix());
    }

    /**
     * Test the block_grade_me_query_suffix function
     */
    public function test_block_grade_me_query_suffix() {
        $expected = " AND bgm.courseid = ?
 AND bgm.itemmodule = 'assign'";
        $this->assertEquals($expected, block_grade_me_query_suffix('assign'));
    }

    /**
     * Dataprovider for testing the cron
     *
     * @return array Grade item data
     */
    public function cron_provider() {
        // Old item.
        $gradeitem1 = new stdClass();
        $gradeitem1->itemid = 3;
        $gradeitem1->itemname = 'assignment2';
        $gradeitem1->itemtype = 'mod';
        $gradeitem1->itemmodule =  'assign';

        // Updated item.
        $gradeitem2 = new stdClass();
        $gradeitem2->itemid = 2;
        $gradeitem2->itemname = 'itemupdate';
        $gradeitem2->itemtype = 'mod';
        $gradeitem2->itemmodule = 'assign';

        // New item.
        $gradeitem3 = new stdClass();
        $gradeitem3->itemid = 1;
        $gradeitem3->itemname = 'newitem';
        $gradeitem3->itemtype = 'mod';
        $gradeitem3->itemmodule = 'assign';

        $data = array(
                array(
                    array(3 => $gradeitem1, 2 => $gradeitem2, 1 => $gradeitem3)
                )
        );

        return $data;
    }

    /**
     * Test the cron
     *
     * @dataProvider cron_provider
     * @param array $expected The expected data
     */
    public function test_cron($expected) {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->create_grade_me_data();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course();

        $grademe = new block_grade_me();
        $grademe->cron();
        $this->expectOutputRegex('/Updated block_grade_me cache in/');
        $actual = $DB->get_records('block_grade_me', array(), '', 'itemid, itemname, itemtype, itemmodule');
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test the block_grade_me_query_quiz function
     */
    public function test_block_grade_me_query_quiz() {
        $expected = ", qa.id state_id, qa.userid, qa.timemodified timesubmitted
        FROM {quiz_attempts} qa
        JOIN {question_sessions} qs ON qs.attemptid = qa.id
        JOIN {quiz} q ON q.id = qa.quiz
        JOIN {quiz_question_instances} qqi ON qqi.quiz = q.id AND qqi.question = qs.questionid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = q.course AND bgm.iteminstance = q.id
       WHERE qa.userid IN (?,?)
             AND qa.timefinish != 0
             AND qqi.grade > 0
             AND qs.newgraded != qs.newest";

        list($sql, $params) = block_grade_me_query_quiz(array(2, 3));
        $this->assertEquals($expected, $sql);
        $this->assertEquals(array(2, 3), $params);
        $this->assertFalse(block_grade_me_query_quiz(array()));
    }

    /**
     * Test the block_grade_me_query_glossary function
     */
    public function test_block_grade_me_query_glossary() {
        global $USER, $DB;

        $concatid = $DB->sql_concat('ge.id', "'-'", $USER->id);
        $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');

        $expected = ", ge.id submissionid, ge.userid, ge.timemodified timesubmitted
        FROM {glossary_entries} ge
        JOIN {glossary} g ON g.id = ge.glossaryid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = g.course AND bgm.iteminstance = ge.id
       WHERE ge.userid IN (?,?)
             AND g.assessed = 1
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

        list($sql, $params) = block_grade_me_query_glossary(array(2, 3));
        $this->assertEquals($expected, $sql);
        $this->assertEquals(array(2, 3), $params);
        $this->assertFalse(block_grade_me_query_glossary(array()));
    }

    /**
     * Test the block_grade_me_query_query_forum function
     */
    public function test_block_grade_me_query_forum() {
        global $USER, $DB;

        $concatid = $DB->sql_concat('fp.id', "'-'", $USER->id);
        $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');

        $expected = ", fp.id submissionid, fp.userid, fp.modified timesubmitted
        FROM {forum_posts} fp
        JOIN {forum_discussions} fd ON fd.id = fp.discussion
        JOIN {forum} f ON f.id = fd.forum
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = f.course AND bgm.iteminstance = f.id
       WHERE fp.userid IN (?,?)
             AND f.assessed = 1
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

        list($sql, $params) = block_grade_me_query_forum(array(2, 3));
        $this->assertEquals($expected, $sql);
        $this->assertEquals(array(2, 3), $params);
        $this->assertFalse(block_grade_me_query_forum(array()));
    }

    /**
     * Test the block_grade_me_query_data function
     */
    public function test_block_grade_me_query_data() {
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
    public function test_block_grade_me_query_assignment() {
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
}
