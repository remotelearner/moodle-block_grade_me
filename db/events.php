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
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2016 Remote Learner.net Inc http://www.remote-learner.net
 */


defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_content_deleted',
        'callback' => '\block_grade_me\quiz_observers::course_content_deleted',
        'internal' => false
    ],
    [
        'eventname' => '\core\event\course_reset_ended',
        'callback' => '\block_grade_me\quiz_observers::course_reset_ended',
        'internal' => false
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\block_grade_me\quiz_observers::course_module_deleted',
        'internal' => false
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_deleted',
        'callback' => '\block_grade_me\quiz_observers::attempt_deleted',
        'internal' => false
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\block_grade_me\quiz_observers::attempt_submitted',
        'internal' => false
    ],
    [
        'eventname' => '\mod_quiz\event\question_manually_graded',
        'callback' => '\block_grade_me\quiz_observers::question_manually_graded',
        'internal' => false
    ],
];
