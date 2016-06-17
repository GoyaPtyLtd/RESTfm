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
interface RESTfmMessageMultistatusInterface {

    /**
     * Get index of record in 'data' that this multistatus applies to.
     *
     * @return integer
     */
    public function getIndex ();

    /**
     * Set index of record in request 'data' that this multistatus applies to.
     * Used by POST/CREATE operations where no recordID exists yet.
     *
     * @param integer $dataRowIndex
     *  Index of row in request's data section that caused the error.
     */
    public function setIndex ($dataRowIndex);

    /**
     * Get status code.
     *
     * @return string
     */
    public function getStatus ();

    /**
     * Set status code.
     *
     * @param integer $statusCode
     */
    public function setStatus ($statusCode);

    /**
     * Get reason message string.
     *
     * @return string
     */
    public function getReason ();

    /**
     * Set reason message string.
     *
     * @param string $reasonMessage
     */
    public function setReason ($reasonMessage);

    /**
     * Get recordID of record in request 'data' that this multistatus applies
     * to.
     *
     * @return string
     */
    public function getRecordId ();

    /**
     * Set recordID of record in request 'data' that this multistatus applies
     * to. Used by GET/READ, PUT/UPDATE and DELETE operations.
     *
     * @param string $recordId
     */
    public function setRecordId ($recordId);
};
