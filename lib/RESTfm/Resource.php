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
 * RESTfm Resource class.
 *
 * Extends Tonic's Resource class for RESTfm specific features.
 */
class Resource extends \Tonic\Resource {

    /**
     * Nothing special in constructor, just pass through to parent.
     */
    function __construct($parameters) {
        // Call parent class constructor.
        parent::__construct($parameters);
    }

    /**
     * Execute a request on this resource.
     *
     * Extension to include handling of OPTIONS HTTP method for cross-site
     * access control (CORS).
     *
     * @param Request $request The request to execute the resource in the
     *  context of.
     *
     * @return RESTfm\Response
     *
     * Overrides tonic's method.
     */
    function exec($request) {

        if (strtoupper($request->method) != 'OPTIONS') {
            // Just use parent exec() as normal.
            return parent::exec($request);
        }

        // Special case the OPTIONS method for cross-site access control (CORS).
        // https://developer.mozilla.org/en-US/docs/HTTP_access_control
        //
        // If we get here then the resource is valid.

        $response = new Response($request);

        if (! isset($_SERVER["HTTP_ORIGIN"])) {
            throw new ResponseException('Invalid request for OPTIONS method.', Response::BADREQUEST);
        }

        // Check request header Origin.
        $request_origin = $_SERVER['HTTP_ORIGIN'];
        $allow_origin = null;
        $configOrigins = Config::getVar('origins', 'allowed');
        if (is_array($configOrigins)) {
            if (in_array('*', $configOrigins)) {
                $allow_origin = '*';
            } else {
                // Case insensitive match for allowed origin.
                foreach ($configOrigins as $origin) {
                    if (strtolower($request_origin) == strtolower($origin)) {
                        $allow_origin = $request_origin;
                    }
                }
            }
        }
        if ($allow_origin == null) {
            throw new ResponseException('Origin forbidden: ' . $request_origin, Response::FORBIDDEN);
        }
        $response->addHeader('Access-Control-Allow-Origin', $allow_origin);

        $allow_methods = $request->allowedMethods;
        $allow_methods[] = 'OPTIONS';
        $response->addHeader('Access-Control-Allow-Methods', join(', ', $allow_methods));

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            $response->addHeader('Access-Control-Allow-Headers', $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
        }

        $response->addHeader('Access-Control-Max-Age', 3600);

        $response->code = Response::OK;

        return $response;
    }

}
