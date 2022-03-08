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

/**
 * @file
 * Modified version of Tonic's dispatch.php
 */

// Wall clock time profiling.
$startTimeUs = microtime(TRUE);

// Ensure E_STRICT is removed for PHP 5.4+
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
//error_reporting(E_ALL & ~E_STRICT);   // Dev. level reporting

// x-debug's html error output makes CLI debugging with cURL a problem.
ini_set('html_errors', FALSE);

// RESTfm autoloader for lib classes.
require_once 'lib/autoload.php';

if (RESTfm\Config::getVar('settings', 'diagnostics') === TRUE) {
    ini_set('display_errors', '1');
} else {
    // Don't display errors to end clients.
    ini_set('display_errors', '0');
}

require_once 'lib/RESTfm/init_paths.php';

// Tonic library
require_once 'lib/tonic/lib/tonic.php';

// Tonic URI resources:
require_once 'lib/uriRoot.php';
require_once 'lib/uriDatabaseConstant.php';
require_once 'lib/uriDatabaseLayout.php';
require_once 'lib/uriDatabaseEcho.php';
require_once 'lib/uriDatabaseScript.php';
require_once 'lib/uriLayout.php';
require_once 'lib/uriScript.php';
require_once 'lib/uriRecord.php';
require_once 'lib/uriField.php';
require_once 'lib/uriFieldName.php';
require_once 'lib/uriBulk.php';

// Ensure we are using SSL if mandated.
if (RESTfm\Config::getVar('settings', 'SSLOnly')) {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ||
            $_SERVER['SERVER_PORT'] == 443) {
        // OK, we are good.
    } else {
        $REQUEST_URI = $_SERVER['REQUEST_URI'];
        // Work around IIS7 mangling of REQUEST_URI when rewriting URLs.
        if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
            $REQUEST_URI = $_SERVER['HTTP_X_ORIGINAL_URL'];
        }

        header("HTTP/1.1 301 Moved Permanently");
        header("Location: https://".$_SERVER['HTTP_HOST'].$REQUEST_URI);
        exit();
    }
}

// Setup tonic config for new request.
$requestConfig = array(
    'baseUri' => RESTfm\Config::getVar('settings', 'baseURI'),
    'acceptFormats' => RESTfm\Config::getFormats(),
);
// Work around IIS7 mangling of REQUEST_URI when rewriting URLs.
if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
    $requestConfig['uri'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
}

// Handle request.
$request = new RESTfm\Request($requestConfig);
RESTfm\Dump::requestData($request);
try {
    if (RESTfm\Config::getVar('settings', 'diagnostics') === TRUE) {
        require_once 'lib/RESTfm/diagnostic_checks.php';
    }
    $request->parse();
    $resource = $request->loadResource();
    $response = $resource->exec($request);

    // Allow the squashing of all 2XX response codes to 200, for clients
    // that can't handle anything else.
    if (preg_match('/^2\d\d$/', $response->code)) {
        $restfmParameters = $request->getParameters();
        if (isset($restfmParameters->RFMsquash2XX)) {
            $response->code = Tonic\Response::OK;     // 200
        }
    }

    RESTfm\Dump::requestParsed($request);
} catch (Tonic\ResponseException $e) {
    switch ($e->getCode()) {
        case Tonic\Response::UNAUTHORIZED:
            // Modify the response code from Unauthorized to Forbidden for
            // data formats handled by applications.

            $response = $e->response($request);
            $format = $request->mostAcceptable(RESTfm\Config::getFormats());
            if ($format != 'html' && $format != 'txt' &&
                    RESTfm\Config::getVar('settings', 'forbiddenOnUnauthorized')) {
                $response->code = Tonic\Response::FORBIDDEN;
                break;
            }

            $response->addHeader('WWW-Authenticate', 'Basic realm="RESTfm"');
            break;

        default:
            $response = $e->response($request);
    }
}

if (RESTfm\Config::getVar('settings', 'diagnostics') === TRUE) {
    // Add profiling information.
    if (is_a($response, 'RESTfm\Response')) {
        // In some early startup errors, we may not be RESTfm\Response, so we
        // checked.

        // Real/wall time (ms)
        // microtime(TRUE) returns seconds as a float.
        $profRealTimeMs = round((microtime(TRUE) - $startTimeUs) * 1000, 0);

        // Peak Memory (human readable bytes)
        // We use the FALSE parameter as we want to know allocated memory
        // against the memory limit. We don't want the "real" usage which is
        // too coarse.
        $profPeakMem = prettyBytes(memory_get_peak_usage(FALSE));

        // Memory Limit (human readable bytes)
        $profLimitMem = prettyBytes(iniToBytes(ini_get('memory_limit')));

        /** @var \RESTfm\Response $response */
        $response->addInfo('X-RESTfm-Profile',  $profRealTimeMs . 'ms ' .
                                                $profPeakMem . ' ' .
                                                $profLimitMem);
    }
}

// Add maximum POST size and memory limit information for all RESTfm 2xx
// responses where a username was specified (non-guest).
if ( is_a($response, 'RESTfm\Response') &&
        preg_match('/^2\d\d$/', $response->code) ) {
    $requestUsername = $request->getCredentials()->getUsername();
    if (! empty($requestUsername)) {
        // All RESTfm URIs perform a database query to validate credentials,
        // so all RESTfm 2xx responses imply successful authorisation.
        /** @var \RESTfm\Response $response */
        $response->addInfo('X-RESTfm-PHP-memory_limit',
                        prettyBytes(iniToBytes(ini_get('memory_limit'))));
        $response->addInfo('X-RESTfm-PHP-post_max_size',
                        prettyBytes(iniToBytes(ini_get('post_max_size'))));
    }
}

// Final response output.
$response->output();
RESTfm\Dump::responseBody($response);

exit;

/**
 * Convert a value read by ini_get() into bytes based on the suffix.
 * Uses the officially described method:
 * http://php.net/manual/en/function.ini-get.php
 *
 * @param string $val
 *
 * @return integer result
 */
function iniToBytes ($val) {
    if ( preg_match('/([0-9]+)\s*([gmk]?)/i', $val, $matches) !== 1 ) {
        return "NaN";   // Not a Number
    }

    if (isset($matches[1])) {
        $val = $matches[1];
    }

    $units = '';
    if (isset($matches[2])) {
        $units = $matches[2];
    }

    switch (strtolower($units)) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

/**
 * Convert bytes in to K/M/G.
 *
 * @param integer $val
 *
 * @return string
 */
function prettyBytes ($val) {
    if ($val == "NaN") {
        return $val;   // Not a Number
    }
    $suffix = '';
    if ($val > 1024) {
        $val /= 1024;
        $suffix = 'K';
    }
    if ($val > 1024) {
        $val /= 1024;
        $suffix = 'M';
    }
    if ($val > 1024) {
        $val /= 1024;
        $suffix = 'G';
    }
    return round($val, 1) . $suffix;
}
