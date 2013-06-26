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
require_once($CFG->dirroot.'/blocks/grade_me/plugins/assign/assign_plugin.php');

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

        // block_grade_me_query_assign returns partial query.
        $partialsql = block_grade_me_query_assign(array($user->id));
        // Build full query.
        $sql = "SELECT a.id, bgm.courseid $partialsql AND bgm.courseid = {$course->id} AND bgm.itemmodule = 'assign'";

        $rec = new stdClass();
        $rec->id = 2;
        $rec->courseid = $course->id;
        $rec->submissionid = 2;
        $rec->userid = $user->id;
        $rec->timesubmitted = '1';

        $expected = array('2' => $rec);
        $actual = $DB->get_records_sql($sql);
        $this->assertEquals($expected, $actual);
    }
}
