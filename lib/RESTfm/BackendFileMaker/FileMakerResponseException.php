<?php
/**
 *  RESTfm - FileMaker RESTful Web Service
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
 * FileMakerResponseException class.
 */
class FileMakerResponseException extends RESTfmResponseException {

    /**
     * Override superclass constructor.
     *
     * @param FileMaker_Error $fileMakerError
     *   The error object as returned from the failed FileMaker command.
     */
    function __construct(FileMaker_Error $fileMakerError) {
        require_once 'FileMaker.php' ;

        $code = 500;                // Default status code. Overridden below.
        $reason = '';

        $fmCode = $fileMakerError->getCode();
        $fmMessage = $fileMakerError->getMessage();
        if ($fmCode == 101 || $fmCode == 104 || $fmCode == 105) {
            // 101: Record is missing
            // 104: Script is missing
            // 105: Layout is missing
            $code = RESTfmResponseException::NOTFOUND;
        } elseif ($fmCode == 22 && stripos($fmMessage, 'password') !== FALSE) {
            // FileMaker Server 11 authorisation required response.
            // "Communication Error: (22) The requested URL returned error:
            //  401 - This can be due to an invalid username or password,
            //  or if the FMPHP privilege is not enabled for that user."
            $code = RESTfmResponseException::UNAUTHORIZED;
        } elseif ($fmCode == 18 && stripos($fmMessage, 'account') !== FALSE) {
            // FileMaker Server 12 authorisation required response.
            // "Client must provide account information to proceed"
            $code = RESTfmResponseException::UNAUTHORIZED;
        } elseif ($fmCode == 9 && stripos($fmMessage, 'privileges') !== FALSE) {
            // FileMaker Server 12 - Sometimes occurs when user account is
            //  correct, but with incorrect password. Does _not_ occur when
            //  trying to write record with read only access.
            // "Insufficient privileges"
            $code = RESTfmResponseException::UNAUTHORIZED;
        }

        // Additional headers for this exception.
        $this->addHeader('X-RESTfm-FM-Status', $fmCode);
        $this->addHeader('X-RESTfm-FM-Reason', $fmMessage);

        // Set a generic FileMaker reason for status 500 if not already set.
        if ($code == 500 && empty($reason)) {
            $reason = 'FileMaker Error';
        }

        // Finally call superclass constructor.
        parent::__construct($reason, $code);
    }

}
