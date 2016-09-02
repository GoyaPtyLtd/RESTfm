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
    public function setRecordId ($recordId);
};
