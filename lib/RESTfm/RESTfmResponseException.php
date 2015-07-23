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

require_once 'RESTfmResponse.php';
require_once 'RESTfmConfig.php';

/**
 * Exception class for HTTP response errors
 */
class RESTfmResponseException extends ResponseException {

    /**
     * HTTP response code constant
     */
    const OK = 200,
          CREATED = 201,
          NOCONTENT = 204,
          MOVEDPERMANENTLY = 301,
          FOUND = 302,
          SEEOTHER = 303,
          NOTMODIFIED = 304,
          TEMPORARYREDIRECT = 307,
          BADREQUEST = 400,
          UNAUTHORIZED = 401,
          FORBIDDEN = 403,
          NOTFOUND = 404,
          METHODNOTALLOWED = 405,
          NOTACCEPTABLE = 406,
          CONFLICT = 409,
          GONE = 410,
          LENGTHREQUIRED = 411,
          PRECONDITIONFAILED = 412,
          UNSUPPORTEDMEDIATYPE = 415,
          INTERNALSERVERERROR = 500;

    /**
     * Constructor - injects exception trace when diagnostics is enabled.
     *
     * @param string $exceptionMessage
     * @param integer $exceptionCode
     *  HTTP Response code.
     * @param Exception $previous
     */
    public function  __construct ($exceptionMessage, $exceptionCode = 0, Exception $previous = null) {
        // Call parent constructor.
        if (version_compare(phpversion(), '5.3.0', '>=')) {
            parent::__construct($exceptionMessage, $exceptionCode, $previous);
        } else {
            parent::__construct($exceptionMessage, $exceptionCode);
        }

        if (RESTfmConfig::getVar('settings', 'diagnostics') === TRUE) {
            $this->addInfo('X-RESTfm-Trace', $this->__toSTring());
        }
    }

    /**
     * Generate a default response for this exception
     *
     * @param RESTfmRequest request
     *
     * @return Response
     */
    public function response($request) {

        $response = new RESTfmResponse($request);

        foreach ($this->_addHeader as $name => $value) {
            $response->addHeader($name, $value);
        }

        foreach ($this->_addInfo as $name => $value) {
            $response->addInfo($name, $value);
        }

        $response->setStatus($this->code, $this->message);

        return $response;
    }

    // -- Protected --

    /**
     * @var array
     *  Associative array of additional HTTP headers to be included in response.
     */
    protected $_addHeader = array();

    /**
     * @var array
     *  Associative array of additional 'info' section content be included
     *  in response.
     */
    protected $_addInfo = array();


    /**
     * Additional HTTP header to be included in response.
     *
     * RESTfmResponse will inject all headers matching /^X-RESTfm-/i into the
     * 'info' section automatically.
     *
     * @var string $header
     * @var string $value
     */
    protected function addHeader($header, $value) {
        $this->_addHeader[$header] = $value;
    }

    /**
     * Additional 'info' section data to be included in response.
     *
     * @var str $name
     * @var str $value
     */
    protected function addInfo($name, $value) {
        $this->_addInfo[$name] = $value;
    }

};
