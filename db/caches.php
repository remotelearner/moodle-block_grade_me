<?php

/**
 * Grademe cache definitions.
 *
 * @package    block_grade_me
 * @category   cache
 * @copyright  2014 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$definitions = array(
    // This MUST NOT be a local cache, sorry cluster lovers.
    'blockhtml' => array(
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true, // The course id or 0 for global.
        'simpledata' => false,
        'ttl' => 14400, // 4 hours - TODO: remove it and use event (submite attempt) to invalid the cache - and even better may to rebuild the cache in background?
//        'staticacceleration' => true,
//        'staticaccelerationsize' => 30,
    ),
);
