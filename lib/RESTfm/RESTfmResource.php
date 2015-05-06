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

require_once 'RESTfmResponse.php' ;
require_once 'RESTfmConfig.php';

/**
 * RESTfmResource class.
 *
 * Extends Tonic's Resource class for RESTfm specific features.
 */
class RESTfmResource extends Resource {

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
     * @return RESTfmResponse
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

        $response = new RESTfmResponse($request);

        if (! isset($_SERVER["HTTP_ORIGIN"])) {
            throw new RESTfmResponseException('Invalid request for OPTIONS method.', Response::BADREQUEST);
        }

        // Check request header Origin.
        $request_origin = $_SERVER['HTTP_ORIGIN'];
        $allow_origin = null;
        $configOrigins = RESTfmConfig::getVar('allowed_origins');
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
            throw new RESTfmResponseException('Origin forbidden: ' . $request_origin, Response::FORBIDDEN);
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

    /**
     * Convert an associative array of fieldName => value pairs, where
     * repetitions are expressed as "fieldName[numericalIndex]" => "value",
     * into the form "fieldName" => array( numericalIndex => "value", ... )
     * i.e. convert from "RESTfm internal format" into "FileMaker add/edit
     * $values format".
     *
     * @param Array $values
     *  Associative array of fieldName => value pairs.
     *
     * @return Array
     *  Associative array where repetitions are converted into a format
     *  suitable for $values parameter of FileMaker API add/edit functions.
     */
    protected function _convertValuesToRepetitions ($values) {
        // Reprocess $values for repetitions compatibility.
        //
        // FileMaker::newAddCommand() / FileMaker::newEditCommand() state
        // that $values / $updatedValues, which contain fieldName => value
        // pairs, should supply a numerically indexed array for the value of
        // any fields with repetitions.
        //
        // The obfuscated constructer of AddImpl.php / EditImpl.php shows
        // that it converts all non-array values into single element arrays
        // internally. This also verifies that the array index must start at
        // zero.
        $valuesRepetitions = array();
        foreach ($values as $fieldName => $value) {
            $matches = array();
            if (preg_match('/^(.+)\[(\d+)\]$/', $fieldName, $matches)) {
                $fieldName = $matches[1];   // Real fieldName minus index.
                $repetition = intval($matches[2]);

                // Use existing array, else construct a new one.
                if ( isset($valuesRepetitions[$fieldName]) &&
                        is_array($valuesRepetitions[$fieldName]) ) {
                    $repeatArray = $valuesRepetitions[$fieldName];
                } else {
                    $repeatArray = array();
                }

                $repeatArray[$repetition] = $value;
                $valuesRepetitions[$fieldName] = $repeatArray;
            } else {
                $valuesRepetitions[$fieldName] = $value;
            }
        }

        return $valuesRepetitions;
    }

}
