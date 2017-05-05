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

namespace RESTfm\Message;

use RESTfm\MessageInterface\MultistatusInterface;

/**
 * Multistatus interface.
 */
class Multistatus implements MultistatusInterface {

    protected $_multiStatus = array();

    /**
     * A multistatus row object for Message.
     *
     * Must set all parameters or none.
     * @param integer $statusCode
     * @param string $reasonMessage
     * @param string $recordId
     */
    public function __construct ($statusCode = NULL, $reasonMessage = NULL, $recordId = NULL) {
        if ($statusCode !== NULL && $reasonMessage !== NULL && $recordId !== NULL) {
            $this->_multiStatus['Status'] = $statusCode;
            $this->_multiStatus['Reason'] = $reasonMessage;
            $this->_multiStatus['recordID'] = $recordId;
        }
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
     * GET/READ, PUT/UPDATE, DELETE Operations:
     * Set recordID of record in request 'data' that this multistatus applies
     * to.
     *
     * POST/CREATE Operations:
     * Set index of record in request 'data' that this multistatus applies to,
     * since no recordID exists yet.
     *
     * @param string $recordId
     */
    public function setRecordId ($recordId) {
        $this->_multiStatus['recordID'] = $recordId;
    }

    /**
     * RESTfm\Message internal function.
     * Return reference to the internal _multiStatus array.
     *
     * @return arrayref _multistatus array.
     */
    public function &_getMultistatusReference () {
        return $this->_multiStatus;
    }
};
