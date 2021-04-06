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

$string['pluginname'] = 'Grade Me';
$string['pluginname-reset'] = 'Grade Me - reset table';
$string['title'] = 'Grade Me';
$string['datetime'] = '%B %d, %l:%M %p';
$string['excess'] = 'There are more than {$a->maxcourses} courses with ungraded work.';
$string['nothing'] = 'Nothing to grade!';

$string['link_gradebook_icon'] = 'Go to {$a->course_name} gradebook…';
$string['link_gradebook'] = 'Go to {$a->course_name}…';
$string['link_mod_img'] = 'Go to {$a->mod_name} in gradebook…';
$string['link_mod'] = 'Go to {$a->mod_name}';
$string['link_grade_img'] = 'Grade assignment…';
$string['link_user_profile'] = '{$a->first_name}\'s profile…';

$string['alt_gradebook'] = 'Go to {$a->course_name} gradebook…';
$string['alt_mod'] = 'Go to {$a->mod_name} in gradebook…';
$string['alt_mark'] = 'check';

$string['settings_maxcourses'] = 'Maximum Courses Displayed';
$string['settings_configmaxcourses'] = 'Set the maximum number of ungraded courses to show. Setting this too high may impact performance.';
$string['settings_maxage'] = 'Maximum Age';
$string['settings_configmaxage'] = 'The maximum age of gradable items, in days, to show. Items older than this will be hidden. Enter 0 for no limit.';
$string['settings_adminviewall'] = 'Admins View All';
$string['settings_configadminviewall'] = 'Enable to give administrators the rights to see all ungraded work — not just for courses where they have a grader role.';
$string['settings_configshowhidden'] = 'Enable showing items to grade within hidden courses';
$string['settings_enablepre'] = 'Show';
$string['settings_configenablepre'] = 'Should Grade Me show unrated activity from the "{$a->plugin_name}" module?';
$string['settings_showhidden'] = 'Hidden course items shown';

$string['grade_me:addinstance'] = 'Add a new Grade Me block';
$string['grade_me:myaddinstance'] = 'Add a new Grade Me block to the My Moodle page';
$string['expand'] = 'Collapse / Expand All';

$string['grade_me_tools'] = 'Tools';
$string['grade_me_tools_desc'] = '<p><a href="{$a}/blocks/grade_me/quiz_update_ngrade.php">Refresh quiz attempts needing grading</a></p>';

$string['quiz_update_ngrade_complete'] = 'Update complete';
$string['quiz_update_ngrade_success'] = 'Quiz attempt list successfully updated, currently there is {$a} questions needing grading.';
