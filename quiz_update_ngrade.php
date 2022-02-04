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

require_once(__DIR__ . '/../../config.php');

$PAGE->set_url(new moodle_url($CFG->wwwroot . '/blocks/grade_me/quiz_update_ngrade.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());
$title = get_string("quiz_update_ngrade_complete", "block_grade_me");
$PAGE->set_title($title);

require_login();
require_capability('moodle/site:config', \context_system::instance());

$DB->delete_records('block_grade_me_quiz_ngrade');
\block_grade_me\quiz_util::update_quiz_ngrade();

echo $OUTPUT->header();
$record = $DB->get_record_sql("SELECT COUNT(DISTINCT questionattemptstepid) count FROM {block_grade_me_quiz_ngrade}");
echo \html_writer::tag('h2', $title);
echo \html_writer::tag('p', get_string("quiz_update_ngrade_success", "block_grade_me", $record->count));
echo $OUTPUT->footer();
