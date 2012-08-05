<?php

function xmldb_block_grade_me_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();
    
    $result = true;
    
    /// Install block_grade_me database
    if ($result && $oldversion < 2012080500) {
        
        // Define table block_grade_me to be created
        $table = new xmldb_table('block_grade_me');
        
        // Adding fields to table block_grade_me
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('itemtype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemmodule', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('iteminstance', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('itemsortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('coursename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coursemoduleid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        
        // Adding keys to table block_grade_me
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('itemid'));
        $table->add_key('itemid', XMLDB_KEY_FOREIGN, array('itemid'), 'grade_items', array('id'));
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('coursemoduleid', XMLDB_KEY_FOREIGN, array('coursemoduleid'), 'course_modules', array('id'));
        
        // Adding indexes to table block_grade_me
        $table->add_index('courseid-itemmodule', XMLDB_INDEX_NOTUNIQUE, array('courseid', 'itemmodule'));
        $table->add_index('itemsortorder', XMLDB_INDEX_NOTUNIQUE, array('itemsortorder'));
        
        // Conditionally launch create table for block_grade_me
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        // grade_me savepoint reached
        upgrade_block_savepoint(true, 2012080500, 'grade_me');
    }
    
    return $result;
}