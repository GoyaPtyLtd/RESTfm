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
 * An extension of RESTfmMessageRow with additional record related metadata
 * access methods.
 */
interface RESTfmMessageRecordInterface {

    /**
     * Return href metadata for record.
     *
     * @return string
     */
    public function getHref ();

    /**
     * Set href metadata for record.
     *
     * @param string $href
     */
    public function setHref ($href);

    /**
     * Return recordID metadata for record.
     *
     * @return string
     */
    public function getRecordId ();

    /**
     * Set recordID metadata for record.
     *
     * @param string $recordId
     */
    public function setRecordId ($recordId);
};
