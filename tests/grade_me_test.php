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

define('CLI_SCRIPT', true);
require_once(dirname(__FILE__) .'/../../../config.php');

class block_grade_me_testcase extends PHPUnit_Framework_TestCase {

    /**
     * Confirm that the block will include the relevant settings.php file
     * for Moodle 2.4.
     */
    public function test_global_configuration_load() {
        $block_inst = block_instance('grade_me');
        $this->assertEquals(true, $block_inst->has_config());
    }
}
