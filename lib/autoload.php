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
    // Autoload function to register:
    function($class) {

        // Ignore autoload requests for these.
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

        // If $class is namespaced, try converting to a path.
        if (strpos($class, '\\') !== FALSE) {
            $classPath =
                __DIR__ . DIRECTORY_SEPARATOR .
                str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
            if (file_exists($classPath)) {
                require_once($classPath);
                return;
            }
        }

        // Not namespaced, so find php file by matching $class against filename
        // no matter where the file is located under lib.

        static $libPhpFiles = NULL; // @var array Basenames of lib php files.
        if ($libPhpFiles === NULL) {
            $libPhpFiles = array();

            // Traverse under $fqpn for $matches ending in $suffix.
            // $matches = array( <basename> => <fqpn>, ... )
            function traverseDirs($fqpn, $suffix, &$matches) {
                $dh = opendir($fqpn);
                while (($childName = readdir($dh))) {
                    if ( ($childName == '.' || $childName == '..')) {
                        continue;
                    }
                    $childFqpn = $fqpn . DIRECTORY_SEPARATOR . $childName;
                    if (is_file($childFqpn) &&
                          (substr($childName, -strlen($suffix)) === $suffix) ) {
                        $matches[basename($childName, $suffix)] = $childFqpn;
                    } elseif (is_dir($childFqpn)) {
                        traverseDirs($childFqpn, $suffix, $matches);
                    }
                }
                closedir($dh);
            }

            // Find all .php files under __DIR__
            traverseDirs(__DIR__, '.php', $libPhpFiles);
        }

        // See if we have a filename that matches $class.
        if (isset($libPhpFiles[$class])) {
            require_once $libPhpFiles[$class];
            return;
        }

        // We never found the right php file :(
        error_log("RESTfm autoload failed for class: $class");
    },
    // Throw exception when autoload function fails to register:
    true,
    // Prepend function on the autoload queue instead of appending it:
    false
);
