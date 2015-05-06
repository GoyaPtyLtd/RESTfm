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

require_once 'RESTfmConfig.php';

/**
 * RESTfm Dump static class.
 */
class RESTfmDump {

    /**
     * @var string Generated directory name as a subdirectory of configured
     *  'dumpPath'.
     */
    private static $_dumpDir = FALSE;

    /**
     * Dump request data to 'requestData.txt' within generated $_dumpDir.
     *
     * Does nothing if 'dumpPath' is not set in config.
     *
     * @param RESTfmRequest $request
     */
    public static function requestData(RESTfmRequest $request) {
        if (RESTfmConfig::getVar('settings', 'dumpData') !== TRUE) {
            // Data dumping not configured.
            return;
        }

        if (!RESTfmDump::createDumpDir()) {
            // Unable to create a subdirectory.
            return;
        }

        file_put_contents(RESTfmDump::$_dumpDir.DIRECTORY_SEPARATOR.'requestData.txt', $request->data);
    }

    /**
     * Dump parsed request data to 'parsedData.txt' within generated $_dumpDir.
     *
     * Does nothing if 'dumpPath' is not set in config.
     *
     * @param RESTfmRequest $request
     */
    public static function requestParsed(RESTfmRequest $request) {
        if (RESTfmConfig::getVar('settings', 'dumpData') !== TRUE) {
            // Data dumping not configured.
            return;
        }

        if (!RESTfmDump::createDumpDir()) {
            // Unable to create a subdirectory.
            return;
        }

        $handle = fopen(RESTfmDump::$_dumpDir.DIRECTORY_SEPARATOR.'parsedData.txt', 'w');

        $restfmParameters = $request->getRESTfmParameters();

        fwrite($handle, "\n" . '------------ Parameters -------------' . "\n");
        fwrite($handle, $restfmParameters);

        fwrite($handle, "\n" . '------------ Data -------------------' . "\n");
        fwrite($handle, $request->getRESTfmData());

        fwrite($handle, "\n" . '------------ RESTfm -----------------' . "\n");
        fwrite($handle, 'request method=' . $request->method .  "\n");

        fwrite($handle, "\n" . '------------ $_SERVER ---------------' . "\n");
        foreach ($_SERVER as $key => $val) {
            fwrite($handle, $key . '="' . addslashes($val) . '"' . "\n");
        }

        fclose($handle);
    }

    /**
     * Dump response data to 'responseBody.txt' within generated $_dumpDir.
     *
     * Does nothing if 'dumpPath' is not set in config.
     *
     * @param Response $response
     */
    public static function responseBody(Response $response) {
        if (RESTfmConfig::getVar('settings', 'dumpData') !== TRUE) {
            // Data dumping not configured.
            return;
        }

        if (!RESTfmDump::createDumpDir()) {
            // Unable to create a subdirectory.
            return;
        }

        file_put_contents(RESTfmDump::$_dumpDir.DIRECTORY_SEPARATOR.'responseBody.txt', $response->body);
    }

    /**
     * Create a unique dump directoy and set the private $_dumpDir variable.
     *
     * Logs an error if unable to do so.
     *
     * @return TRUE on success.
     */
    protected static function createDumpDir() {
        if (RESTfmDump::$_dumpDir !== FALSE) {
            // We have already set one up.
            return TRUE;
        }

        if (RESTfmConfig::getVar('settings', 'dumpData') !== TRUE) {
            // Data dumping not configured.
            return FALSE;
        }

        // We suppress errors here, as we don't care if date.timezone is not
        // configured in php.ini, let PHP guess without raising a warning.
        $timestamp = @date('YmdHis', time());

        // Create a temporary file name.
        $tempName = tempnam(sys_get_temp_dir(), 'restfmdump.'.$timestamp);
        if ($tempName === FALSE) {
            // Failed to create a file.
            error_log('RESTfmDump::createDumpDir(): failed to create a restfmdump directory.');
            return FALSE;
        }

        // Use the temporary file name as the basis for a new directory name.
        RESTfmDump::$_dumpDir = $tempName.'.d';
        mkdir(RESTfmDump::$_dumpDir);
        unlink($tempName);

        return TRUE;
    }

};
