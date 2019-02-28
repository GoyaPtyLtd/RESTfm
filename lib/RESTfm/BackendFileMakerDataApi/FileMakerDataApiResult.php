<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2019 Goya Pty Ltd.
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
 * Result from Querying FileMaker Data API. Methods are intended to be similar
 * to those in the FileMaker PHP API Result class.
 */
class FileMakerDataApiResult {

    /**
     * @var string
     */
    private $_result = NULL;

    /**
     * @param array $result
     *  Result from querying FileMaer Data API.
     */
    public function __construct ($result) {
        $this->_result = $result;
    }

    /**
     * Return code from result of querying FileMaker Data API.
     */
    public function getCode () {
        return @$this->_result['messages'][0]['code'];
    }

    /**
     * Return the number of records in this result.
     */
    public function getFetchCount () {
        return count(@$this->_result['response']['data']);
    }

    /**
     * Return the first record in this result.
     * @return array
     */
    public function getFirstRecord () {
        return @$this->_result['response']['data'][0];
    }

    /**
     * Return message from result of querying FileMaker Data API.
     */
    public function getMessage () {
        return @$this->_result['messages'][0]['message'];
    }

    /**
     * Return all records from result of querying FileMaker Data API.
     */
    public function getRecords () {
        return @$this->_result['response']['data'];
    }

    /**
     * Return token from result of querying FileMaker Data API.
     */
    public function getToken () {
        return @$this->_result['response']['token'];
    }

    /**
     * Returns TRUE if response contains a FileMaker Data API Server error.
     *
     * @param array $response
     *  Response array decoded from FileMaker Data API Server JSON.
     *
     * @return bool
     *  TRUE on non zero 'code',
     *  TRUE on 'message' present but no 'code'
     *  else FALSE.
     *
     * @throws \RESTfm\ResponseException
     *  If 'response' does not contain a key named 'code'. i.e. invlaid
     */
    public function isError () {
        if (@isset($this->_result['messages'][0]['code']) ) {
            if ($this->_result['messages'][0]['code'] !== '0') {
                return TRUE;
            }
        } elseif (@isset($this->_result['messages'][0]['message'])) {
            return TRUE;
        } else {
            // Invalid response.
            error_log('RESTfm FileMakerDataApiResult::isError() invalid: ' . serialize($_result));
            throw new \RESTfm\ResponseException(
                            'Invalid response from FMDataAPI Server',
                            \RESTfm\ResponseException::INTERNALSERVERERROR);
        }
        return FALSE;
    }




}
