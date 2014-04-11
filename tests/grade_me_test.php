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
     * Load the testing dataset. Meant to be used by any tests that require the testing dataset.
     */
    protected function create_grade_me_data() {
        $this->loadDataSet($this->createXMLDataSet(__DIR__.'/fixtures/block_grade_me.xml'));
    }

    /**
     * Ensure that we can load our test dataset into the current DB.
     */
    public function test_grade_me_load_db() {
        $this->resetAfterTest(true);
        $this->create_grade_me_data();
    }

    /**
     * Test the function block_grade_me_query_assign.
     */
    public function test_block_grade_me_query_assign() {
        global $DB;

        $this->resetAfterTest(true);
        $this->create_grade_me_data();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Partial query return from block_grade_me_query_assign.
        list($sql, $insqlparams) = block_grade_me_query_assign(array($user->id));
        // Build full query.
        $sql = "SELECT a.id, bgm.courseid $sql AND bgm.courseid = {$course->id} AND bgm.itemmodule = 'assign'";

        $rec = new stdClass();
        $rec->id = '3';
        $rec->courseid = $course->id;
        $rec->submissionid = '2';
        $rec->userid = $user->id;
        $rec->timesubmitted = '2';

        $rec2 = new stdClass();
        $rec2->id = '4';
        $rec2->courseid = $course->id;
        $rec2->submissionid = '3';
        $rec2->userid = $user->id;
        $rec2->timesubmitted = '3';

        $expected = array('3' => $rec, '4' => $rec2);
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
        $item5->itemname = 'testassignment5';
        $item5->itemtype = 'mod';
        $item5->itemmodule = 'assignment';

        // Represents updated record.
        $item6 = new StdClass();
        $item6->id = 6;
        $item6->itemname = 'testassignment6_UPDATED';
        $item6->itemtype = 'mod';
        $item6->itemmodule = 'assignment';

        $data = array(
                array(
                    array(
                        '2' => $item2,
                        '3' => $item3,
                        '4' => $item4,
                        '5' => $item5,
                        '6' => $item6
                    )
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
        $actual = $DB->get_records('block_grade_me', array(), '', 'id, itemname, itemtype, itemmodule');
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

    /**
     * Test the function get_content for one user in the gradebook for a course.
     * Check that urls returned are what they should be
     * @dataProvider grade_me_plugin_single_user_provider
     * @param string plugin The name of the plugin being tested
     * @param array  expected values  An array of values that should be found in the grade_me block output
     */
    public function test_block_grade_me_get_content_single_user($plugin, $expectedvalues) {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);
        $this->create_grade_me_data();

        // Make sure that the plugin being tested has been enabled
        if (!$CFG->{'block_grade_me_enable'.$plugin} == true) {
            set_config('block_grade_me_enable'.$plugin, true);
        }

        if (!$CFG->block_grade_me_enableadminviewall) {
            set_config('block_grade_me_enableadminviewall', true);
        }

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $adminuser = $this->getDataGenerator()->create_user();
        $this->setAdminUser($adminuser);
        $course = $this->getDataGenerator()->create_course();

        // Set up gradebook role
        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        $roleid = create_role('role', 'role', 'grade me block');
        set_role_contextlevels($roleid, array(CONTEXT_COURSE));
        role_assign($roleid, $user->id, $context->id);
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
            'userid' => $user->id
        ));

        $grademe = new block_grade_me();
        $content = $grademe->get_content();

        foreach ($expectedvalues as $expected) {
            $this->assertRegExp($expected, $content->text);
        }
    }

    /**
     * Provide input data to the parameters of the test_block_grade_me_get_content_single_user() method.
     */
    public function grade_me_plugin_single_user_provider() {
        return array(
                   array("assign",
                       array(1 => "/Go to assign/",
                             2 => "/mod\/assign\/view.php/",
                             3 => "/action=grade&rownum=0&userid=3/",
                             5 => "/testassignment3/",
                             6 => "/testassignment4/"
                             )
                       ),
                   // Test multiple assignments
                   array("assignment",
                       array(1 => "/Go to assignment/",
                             2 => "/mod\/assignment\/submissions.php/",
                             3 => "/userid=3&amp;mode=single/",
                             5 => "/testassignment5/",
                             6 => "/testassignment6/",
                             )
                       )
        );
    }

    /**
     * Test the function get_content.
     * Check that urls returned are what they should be
     * @dataProvider grade_me_plugin_multiple_user_provider
     * @param string plugin The name of the plugin being tested
     * @param array  expected values  An array of values that should be found in the grade_me block output
     */
    public function test_block_grade_me_get_content_multiple_user($plugin, $expectedvalues) {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);
        $this->create_grade_me_data();

        // Make sure that the plugin being tested has been enabled
        if (!$CFG->{'block_grade_me_enable'.$plugin} == true) {
            set_config('block_grade_me_enable'.$plugin, true);
        }

        if (!$CFG->block_grade_me_enableadminviewall) {
            set_config('block_grade_me_enableadminviewall', true);
        }

        // When testing with multiple users
        // Need multiple gradebookroles and timemodified needs to be different on submission
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $user2 = $this->getDataGenerator()->create_user();
        $adminuser = $this->getDataGenerator()->create_user();
        $this->setAdminUser($adminuser);
        $course = $this->getDataGenerator()->create_course();

        // Set up gradebook roles
        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        $roleid = create_role('role', 'role', 'grade me block');
        $roleid2 = create_role('role2', 'role2', 'grade me block');
        set_role_contextlevels($roleid, array(CONTEXT_COURSE));
        role_assign($roleid, $user->id, $context->id);
        role_assign($roleid2, $user2->id, $context->id);
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
            'userid' => $user->id
        ));
        $DB->insert_record('user_enrolments', (object)array(
            'status' => 0,
            'enrolid' => $enrolid,
            'userid' => $user2->id
        ));

        $grademe = new block_grade_me();
        $content = $grademe->get_content();

        foreach ($expectedvalues as $expected) {
            $this->assertRegExp($expected, $content->text);
        }
    }

    /**
     * Provide input data to the parameters of the test_censusreport_null_grade_check() method.
     */
    public function grade_me_plugin_multiple_user_provider() {
        return array(
                   array("assign",
                           array(1 => "/Go to assign/",
                                 2 => "/mod\/assign\/view.php/",
                                 3 => "/action=grade&rownum=0&userid=3/",
                                 4 => "/action=grade&rownum=1&userid=4/",
                                 5 => "/testassignment3/",
                                 6 => "/testassignment4/"
                                 )
                           ),
                   // Test multiple assignments
                   array("assignment",
                           array(1 => "/Go to assignment/",
                                 2 => "/mod\/assignment\/submissions.php/",
                                 3 => "/userid=3&amp;mode=single/",
                                 4 => "/userid=4&amp;mode=single/",
                                 5 => "/testassignment5/",
                                 6 => "/testassignment6/",
                                 )
                           )
        );
    }
}
