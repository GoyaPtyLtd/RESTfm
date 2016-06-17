<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2016 Goya Pty Ltd.
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
 * Multistatus interface.
 */
class RESTfmMessageMultistatus implements RESTfmMessageMultistatusInterface {

    protected $_multiStatus = array();

    /**
     * Get index of record in 'data' that this multistatus applies to.
     *
     * @return integer
     */
    public function getIndex () {
        if (isset($this->_multiStatus['index'])) return $this->_multiStatus['index'];
    }

    /**
     * Set index of record in request 'data' that this multistatus applies to.
     * Used by POST/CREATE operations where no recordID exists yet.
     *
     * @param integer $dataRowIndex
     *  Index of row in request's data section that caused the error.
     */
    public function setIndex ($dataRowIndex) {
        $this->_multiStatus['index'] = $dataRowIndex;
    }

    /**
     * Get status code.
     *
     * @return string
     */
    public function getStatus () {
        if (isset($this->_multiStatus['Status'])) return $this->_multiStatus['Status'];
    }

    /**
     * Set status code.
     *
     * @param integer $statusCode
     */
    public function setStatus ($statusCode) {
        $this->_multiStatus['Status'] = $statusCode;
    }

    /**
     * Get reason message string.
     *
     * @return string
     */
    public function getReason () {
        if (isset($this->_multiStatus['Reason'])) return $this->_multiStatus['Reason'];
    }

    /**
     * Set reason message string.
     *
     * @param string $reasonMessage
     */
    public function setReason ($reasonMessage) {
        $this->_multiStatus['Reason'] = $reasonMessage;
    }

    /**
     * Get recordID of record in request 'data' that this multistatus applies
     * to.
     *
     * @return string
     */
    public function getRecordId () {
        if (isset($this->_multiStatus['recordID'])) return $this->_multiStatus['recordID'];
    }

    /**
     * Set recordID of record in request 'data' that this multistatus applies
     * to. Used by GET/READ, PUT/UPDATE and DELETE operations.
     *
     * @param string $recordId
     */
    public function setRecordId ($recordId) {
        $this->_multiStatus['recordID'] = $recordId;
    }

    /**
     * RESTfmMessage internal function.
     * Return reference to the internal _multiStatus array.
     *
     * @return arrayref _multistatus array.
     */
    public function &_getMultistatusReference () {
        return $this->_multiStatus;
    }
};
