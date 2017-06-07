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

namespace RESTfm\BackendFileMakerDataApi;

/**
 * Exception class errors from the FileMaker Data API Server.
 */
class FileMakerDataApiResponseException extends \RESTfm\ResponseException {

    /**
     * Override superclass constructor.
     *
     * @param array $response
     *  Response array decoded from FileMaker Data API Server JSON.
     */
    function __construct ($response) {

        $code = 500;                // Default status code. Overridden below.
        $reason = '';

        // Exctract error code and message from response.
        $fmDataApiCode = '';
        $fmDataApiMessage = '';
        if (isset($response['errorCode'])) {
            $fmDataApiCode = $response['errorCode'];
        }
        if (isset($response['errorMessage'])) {
            $fmDataApiMessage = $response['errorMessage'];
        }

        // Manage cases that map to HTTP Not Found & HTTP Unauthorized
        if ($fmDataApiCode == 212) {
            // "Invalid user account and/or password; please try again"
            $code = \RESTfm\ResponseException::UNAUTHORIZED;
        }

        // Additional headers for this exception.
        $this->addHeader('X-RESTfm-FMDataAPI-Status', $fmDataApiCode);
        $this->addHeader('X-RESTfm-FMDataAPI-Reason', $fmDataApiMessage);

        // Set a generic reason for status 500 if not already set.
        if ($code == 500 && empty($reason)) {
            $reason = 'FileMaker Data API Server Error';
        }

        // Finally call superclass constructor.
        parent::__construct($reason, $code);
    }

}
