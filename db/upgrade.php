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

    // Moodle v2.4.0 release upgrade line
    // Put any upgrade step following this.

    if ($oldversion < 2013022600) {
        // Get the instances of this block.
        if ($blocks = $DB->get_records('block_instances', array('blockname' => 'grade_me', 'pagetypepattern' => 'my-index'))) {
            // Loop through and remove them from the My Moodle page.
            foreach ($blocks as $block) {
                blocks_delete_instance($block);
            }

        }

        // Grade_me savepoint reached.
        upgrade_block_savepoint(true, 2013022600, 'grade_me');
    }

    if ($oldversion < 2013051402) {
        // Rename and redefine old field name 'itemid' from table table block_grade_me to 'id'.
        $table = new xmldb_table('block_grade_me');
        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'id');
        }

        upgrade_block_savepoint(true, 2013051402, 'grade_me');
    }

    if ($oldversion < 2015102402) {
        if (!$dbman->table_exists('block_grade_me_quiz_ngrade')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'block_grade_me_quiz_ngrade');
        }
        $DB->delete_records('block_grade_me_quiz_ngrade');
        // Pre populate block_grade_me_quiz_ngrade table.
        \block_grade_me\quiz_util::update_quiz_ngrade();
        upgrade_block_savepoint(true, '2015102402', 'grade_me');
    }

    if ($oldversion < 2016120503) {

        // Define index itemmodule (not unique) to be added to grade_me.
        $table = new xmldb_table('block_grade_me');
        $index = new xmldb_index('itemmodule', XMLDB_INDEX_NOTUNIQUE, array('itemmodule'));

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index iteminstance (unique) to be added to grade_me.
        $table = new xmldb_table('block_grade_me');
        $index = new xmldb_index('iteminstance', XMLDB_INDEX_NOTUNIQUE, array('iteminstance'));

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Grade me  savepoint reached.
        upgrade_block_savepoint(true, 2016120503, 'grade_me');
    }

    if ($oldversion < 2024013100) {
        $table = new xmldb_table('block_grade_me');

        // Define key id (foreign) to be dropped form block_grade_me.
        $key = new xmldb_key('id', XMLDB_KEY_FOREIGN, ['id'], 'grade_items', ['id']);

        // Launch drop key id. There is no key_exists method.
        $dbman->drop_key($table, $key);

        // Define field itemid to be added to block_grade_me.
        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');

        // Conditionally launch add field itemid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Populate the itemid field.
        $DB->execute('UPDATE {block_grade_me} SET itemid = id');

        // Set itemid to notnull
        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }

        // Define key gradeitemid (foreign) to be added to block_grade_me.
        $key = new xmldb_key('gradeitemid', XMLDB_KEY_FOREIGN, ['itemid'], 'grade_items', ['id']);

        // Launch add key gradeitemid. There is no key_exists method.
        $dbman->add_key($table, $key);

        // Grade_me savepoint reached.
        upgrade_block_savepoint(true, 2024013100, 'grade_me');
    }

    return true;
}
