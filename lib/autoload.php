<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2016 Goya Pty Ltd.
 *
 * @license
 *  Licensed under The MIT License. For full copyright and license information,
 *  please see the LICENSE file distributed with this package.
 *  Redistributions of files must retain the above copyright notice.
 *
 * @link
 *  http://restfm.com
 *
 * @author
 *  Gavin Stewart
 */

 // Register an autoload function for RESTfm class files.
 spl_autoload_register(
    function($class) {
        static $ignore = NULL;
        if ($ignore === NULL) {
            $ignore = array(
                    '/^Tonic\\\/',
                    '/^Composer\\\/',
            );
        }

        foreach ($ignore as $ignoreRegex) {
            if (preg_match($ignoreRegex, $class) === 1) {
                # DEBUG log
                #error_log("Matched ignore regex: $ignoreRegex");
                return;
            }
        }

        static $classes = NULL;
        if ($classes === NULL) {
            $classes = array(
                    'RESTfmMessage' => '/RESTfm/RESTfmMessage/RESTfmMessage.php',
                    'RESTfmMessageRecord' => '/RESTfm/RESTfmMessage/RESTfmMessageRecord.php',
                    'RESTfmMessageRow' => '/RESTfm/RESTfmMessage/RESTfmMessageRow.php',
                    'RESTfmMessageMultistatus' => '/RESTfm/RESTfmMessage/RESTfmMessageMultistatus.php',
                    'RESTfmMessageSection' => '/RESTfm/RESTfmMessage/RESTfmMessageSection.php',
                    'RESTfmMessageInterface' => '/RESTfm/RESTfmMessageInterface/RESTfmMessageInterface.php',
                    'RESTfmMessageRecordInterface' => '/RESTfm/RESTfmMessageInterface/RESTfmMessageRecordInterface.php',
                    'RESTfmMessageRowInterface' => '/RESTfm/RESTfmMessageInterface/RESTfmMessageRowInterface.php',
                    'RESTfmMessageMultistatusInterface' => '/RESTfm/RESTfmMessageInterface/RESTfmMessageMultistatusInterface.php',
                    'RESTfmMessageSectionInterface' => '/RESTfm/RESTfmMessageInterface/RESTfmMessageSectionInterface.php',
            );
        }

        if (isset($classes[$class])) {
            require_once __DIR__ . $classes[$class];
        } else {
            error_log("RESTfm autoload failed for class: $class");
        }
    },
    true,
    false
);
