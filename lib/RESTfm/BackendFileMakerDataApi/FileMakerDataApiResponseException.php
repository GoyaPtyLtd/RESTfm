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
 * Exception class for HTTP response errors
 */
class FileMakerDataApiResponseException extends \RESTfm\ResponseException {

    /**
     * Override superclass constructor.
     *
     */
    function __construct ($reason, $code = 500, \Exception $previous = null) {

        // Additional headers for this exception.
        $this->addHeader('X-RESTfm-FMDataAPI-Status', $code);
        $this->addHeader('X-RESTfm-FMDataAPI-Reason', $reason);

        // Set a generic reason for status 500 if not already set.
        if ($code == 500 && empty($reason)) {
            $reason = 'FileMaker Data API Error';
        }

        // Finally call superclass constructor.
        parent::__construct($reason, $code, $previous);
    }

}
