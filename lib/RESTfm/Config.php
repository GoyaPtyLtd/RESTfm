<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2017 Goya Pty Ltd.
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

namespace RESTfm;

/**
 * RESTfm Config static class.
 */
class Config {

    /**
     * @var array Cached copy of config array.
     */
    private static $_config;

    const CONFIG_INI = 'RESTfm.ini.php';

    /**
     * Checks the existence of the config variable requested.
     *
     * No logging occurs for non-existent variables.
     *
     * @param string $variableName
     *  Name of config variable to return.
     * @param string ...
     *  Name of sub-config variable to return ...
     *
     * @return boolean
     *  TRUE if variable exists.
     */
    public static function checkVar() {
        // Require at least one argument.
        if (func_num_args() < 1) {
            return FALSE;
        }

        // Fetch top level of config.
        $result = Config::_getConfig();

        // For each function argument; descend config.
        foreach(func_get_args() as $arg) {
            if (! isset($result[$arg])) {
                return FALSE;
            }
            $result = $result[$arg];
        }

        return TRUE;
    }

    /**
     * Returns the contents of the config variable requested.
     *
     * Any request for an undefined variable will be logged with information
     * on the calling function.
     *
     * @param string $variableName
     *  Name of config variable to return.
     * @param string ...
     *  Name of sub-config variable to return ...
     *
     *  e.g.:
     *    Returns an associative array: 'settings', 'formats'
     *    Returns a single element: 'settings', 'SSLOnly'
     *    (See RESTfm.ini.php to understand why these two similar looking
     *     argument pairs return different data types.)
     *
     * @return mixed
     *  Variable requested | NULL if non-existent.
     */
    public static function getVar() {
        // Require at least one argument.
        if (func_num_args() < 1) {
            return NULL;
        }

        // Fetch top level of config.
        $result = Config::_getConfig();

        // For each function argument; descend config.
        foreach(func_get_args() as $arg) {
            if (! isset($result[$arg])) {
                //var_dump(debug_backtrace());
                error_log('RESTfm\Config::getVar() error: Request for non-existent variable: "' . join('", "', func_get_args()) . '", caller: ' .
                          Config::_backtraceStr(debug_backtrace()));
                return NULL;
            }
            $result = $result[$arg];
        }

        return $result;
    }

    /**
     * Returns an array of allowed communication formats.
     *
     * @return array
     */
    public static function getFormats() {
        if (!self::$_config) {
            self::_getConfig();
        }
        return self::$_config['settings']['formats'];
    }

    // -- Private Methods --

    /**
     * Returns an associative array of the configuration structure.
     *
     * @return array
     */
    private static function _getConfig() {
        if (!self::$_config) {
            include_once self::CONFIG_INI;
            self::$_config = $config;
        }

        // DEBUG current config and new ini format
        //var_export(self::$_config);
        //echo "\n";

        $iniConfig = parse_ini_file('RESTfm.ini', true, INI_SCANNER_TYPED);
        var_export($iniConfig);
        echo "\n";

        // Include other ini files
        $iniFiles = array();
        if (isset($iniConfig['config']['include']) &&
                is_array($iniConfig['config']['include'])) {
            foreach ($iniConfig['config']['include'] as $dir) {
                if (is_dir($dir)) {
                    if ($dh = opendir($dir)) {
                        while (($filename = readdir($dh)) !== false) {
                            $withPath = $dir . DIRECTORY_SEPARATOR . $filename;
                            if (is_file($withPath) &&
                                    substr_compare($filename, '.ini', -4, 4, true) === 0) {
                                    $iniFiles[] = $withPath;
                            }
                        }
                        closedir($dh);
                    }
                }
            }
        }
        sort($iniFiles);
        while (! empty($iniFiles)) {
            $filename = array_shift($iniFiles);
            echo "Parse: $filename\n";
            $iniInclude = parse_ini_file($filename, true, INI_SCANNER_TYPED);
            var_export($iniInclude);
            echo "\n";
            $iniConfig = array_merge_recursive($iniConfig, $iniInclude);
        }

        var_export($iniConfig);
        echo "\n";

        // Notes on how we want to recurse the inclusion of ini files:
        //  - read whole ini, relative include dir(s) discovered
        //  - foreach relative dir:
        //      - read all *.ini filenames, sort
        //      - foreach ini filename:
        //          - read whole ini, relative include dir(s) discovered
        //          . . .
        //
        // $path = '.';
        // $config = recursiveMergeIni($path, $config)

        exit;

        return self::$_config;
    }

    /**
     * Returns a simple string defining the calling function as best possible
     * from the provided stacktrace.
     *
     * @param array $backtrace
     *   The caller should provide debug_backtrace().
     *
     * @return string
     */
    private static function _backtraceStr($backtrace) {
        $a = array();
        if (isset($backtrace[0]['file'])) {
            array_push($a, basename($backtrace[0]['file']));
        }
        if (isset($backtrace[0]['line'])) {
            array_push($a, '#' . basename($backtrace[0]['line']));
        }
        if (isset($backtrace[1]['class'])) {
            array_push($a, basename($backtrace[1]['class'] . ':'));
        }
        if (isset($backtrace[1]['function'])) {
            array_push($a, basename($backtrace[1]['function'] . '()'));
        }
        return join(':', $a);
    }

}
