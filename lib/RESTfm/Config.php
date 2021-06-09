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
     * @param string $configFilename
     *  Filename to read config from.
     *
     * @return array
     */
    private static function _getConfig($configFilename = self::CONFIG_INI) {
        if (!self::$_config) {
            include_once $configFilename;
            self::$_config = $config;
        }

        // DEBUG current config and new ini format
        //var_export(self::$_config);
        //echo "\n";

        $config = array();
        $path = '.';
        $filename = 'RESTfm.ini';
        self::_recursiveMergeConfig($config, $path, $filename);

        echo "Final config:\n";
        var_export($config);
        echo "\n";

        exit;

        return self::$_config;
    }

    /**
     * Load config from $path + $filename and merge into $config array,
     * recursively merging config files from include directories.
     *
     * @param array $config
     *  Associative array containing config loaded so far.
     * @param string $path
     *  Relative path we are loading $filename from.
     * @param string $filename
     *  Filename of config to load and merge into $config.
     */
    private static function _recursiveMergeConfig(&$config, $path, $filename) {

        // Load specified config file.
        $relativeName = $path . DIRECTORY_SEPARATOR . $filename;
        echo "Parse: $relativeName\n";
        $configData = parse_ini_file($relativeName, true, INI_SCANNER_TYPED);
        var_export($configData);
        echo "\n";
        if ($configData === false) {
            // Failed to parse file as ini.
            return;
        }
        // Keep a record of config files in order they were included
        $configData['config']['included'][] = $relativeName;

        // Merge loaded config with existing config.
        $config = self::_arrayMergeRecursive($config, $configData);

        // Identify other config files from include directories just loaded.
        if (isset($configData['config']['include']) &&
                    is_array($configData['config']['include'])) {
            foreach ($configData['config']['include'] as $dir) {
                $relativePath = $path . DIRECTORY_SEPARATOR . $dir;
                if (is_dir($relativePath)) {
                    if ($dh = opendir($relativePath)) {
                        $configFileList = array();
                        while (($checkName = readdir($dh)) !== false) {
                            // Remember all files that look like *.ini
                            if (substr_compare(
                                    $checkName, '.ini', -4, 4, true) === 0) {
                                $configFileList[] = $checkName;
                            }
                        }
                        closedir($dh);

                        // Sort array of filenames into alphanumeric order.
                        sort($configFileList);

                        // Recursively load and merge config files include dir.
                        foreach ($configFileList as $configFileName) {
                            self::_recursiveMergeConfig(
                                    $config,
                                    $relativePath,
                                    $configFileName
                            );
                        }
                    }
                }
            }
        }

        // Remove ['config']['include'] section as data is nonsensical without
        // path data relative to top level config. We use ['config']['included']
        // section to describe this better.
        unset($config['config']['include']);
    }

    /**
     * Reimplementation of PHP's array_merge_recursive(), but without
     * ever modifying the first array's value's types. PHP's version will
     * change a value to an array if two non-array values for the same key
     * are merged, this is dumb, we let the merged value overwrite instead.
     *
     * @param array $a1
     * @param array $a2
     *
     * @return array
     *  Result of merging $a2 into $a1.
     */
    private static function _arrayMergeRecursive (array &$a1, array &$a2) {
        $dest = $a1;                        // Start with a copy of $a1.

        foreach ($a2 as $key => &$val) {    // Walk $a2 (this is src).
            if (is_array($val) && isset($dest[$key]) && is_array($dest[$key])) {
                // iff $key exists in src and dest, and val is array in both.
                $dest[$key] = self::_arrayMergeRecursive($dest[$key], $val);
            } elseif (is_integer($key) && isset($dest[$key])) {
                // We guess dest is a sequential array so append src val.
                $dest[] = $val;
            } else {
                // Fallthrough: force src val.
                $dest[$key] = $val;
            }
        }

        return $dest;
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
