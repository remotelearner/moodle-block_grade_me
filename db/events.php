<?php

/**
 * Definition of grade_me event observers.
 *
 * The observers defined in this file are notified when respective events are triggered. All plugins
 * support this.
 *
 * For more information, take a look to the documentation available:
 *     - Events API: {@link http://docs.moodle.org/dev/Event_2}
 *     - Upgrade API: {@link http://docs.moodle.org/dev/Upgrade_API}
 *
 * @package   core
 * @category  event
 * @copyright 2015 VLACS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname'   => '\mod_assign\event\assessable_submitted',
        'callback'    => 'block_grade_me_observer::update_assign_cron_tasks',
    ),
    array(
        'eventname'   => '\mod_quiz\event\attempt_submitted',
        'callback'    => 'block_grade_me_observer::update_quiz_cron_tasks',
    )
    // TODO other activity events - they are currently not used by vlacs.
);

// List of all events triggered by Moodle can be found using Events list report.

