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
 * This file keeps track of upgrades to the self completion block
 *
 * Sometimes, changes between versions involve alterations to database structures
 * and other major things that may break installations.
 *
 * The upgrade function in this file will attempt to perform all the necessary
 * actions to upgrade your older installation to the current version.
 *
 * If there's something it cannot do itself, it will tell you what you need to do.
 *
 * The commands in here will all be database-neutral, using the methods of
 * database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @since 2.0
 * @package blocks
 * @copyright 2013 Dakota Duff <http://www.remote-learner.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles upgrading instances of this block.
 *
 * @param int $oldversion
 * @param object $block
 */
function xmldb_block_grade_me_upgrade($oldversion, $block) {
    global $DB;

    $dbman = $DB->get_manager();

    $result = true;

    // Install block_grade_me database.
    if ($result && $oldversion < 2012080500) {

        // Define table block_grade_me to be created.
        $table = new xmldb_table('block_grade_me');

        // Adding fields to table block_grade_me.
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('itemtype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemmodule', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('iteminstance', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('itemsortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('coursename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coursemoduleid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Adding keys to table block_grade_me.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('itemid'));
        $table->add_key('itemid', XMLDB_KEY_FOREIGN, array('itemid'), 'grade_items', array('id'));
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('coursemoduleid', XMLDB_KEY_FOREIGN, array('coursemoduleid'), 'course_modules', array('id'));

        // Adding indexes to table block_grade_me.
        $table->add_index('courseid-itemmodule', XMLDB_INDEX_NOTUNIQUE, array('courseid', 'itemmodule'));
        $table->add_index('itemsortorder', XMLDB_INDEX_NOTUNIQUE, array('itemsortorder'));

        // Conditionally launch create table for block_grade_me.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Savepoint reached.
        upgrade_block_savepoint(true, 2012080500, 'grade_me');
    }

    if ($result && ($oldversion < 2012080501)) {
        // Get the instances of this block.
        if ($blocks = $DB->get_records('block_instances', array('blockname' => 'grade_me', 'pagetypepattern' => 'my-index'))) {
            // Loop through and remove them from the My Moodle page.
            foreach ($blocks as $block) {
                blocks_delete_instance($block);
            }

        }

        // Savepoint reached.
        upgrade_block_savepoint(true, 2012080501, 'grade_me');
    }

    return true;
}
