<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2015 Goya Pty Ltd.
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

/**
 * Version static class to hold release version.
 */
class Version {
    private static $_release     = 'dev';
    private static $_revision    = '%%REVISION%%';
    private static $_protocol    = '5';     // Bump this when REST API changes.

    public static function getRelease() {
        return self::$_release;
    }

    public static function getRevision() {
        return self::$_revision;
    }

    public static function getVersion() {
        $revision = self::$_revision;
        if (strpos($revision, 'REVISION') !== FALSE) {
            $revision = 'UNKNOWN';
        }

        return self::$_release . '/' . $revision;
    }

    public static function getProtocol() {
        return self::$_protocol;
    }
}

// We only execute this if called directly from the command line,
// not by a web server.
if (php_sapi_name() == "cli") {
    global $argv;

    if (count($argv) > 1) {
        switch($argv[1]) {
            case '-r':
                echo Version::getRelease();
                return;

            case '-p':
                echo Version::getProtocol();
                return;
        }
    }

    echo Version::getVersion();
}
