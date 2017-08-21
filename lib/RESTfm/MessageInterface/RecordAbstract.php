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

namespace RESTfm\MessageInterface;

/**
 * An extension of RESTfm\Message\Row with additional record related metadata
 * access methods.
 */
abstract class RecordAbstract extends \RESTfm\Message\Row {

    /**
     * Return href metadata for record.
     *
     * @return string
     */
    abstract public function getHref ();

    /**
     * Set href metadata for record.
     *
     * @param string $href
     */
    abstract public function setHref ($href);

    /**
     * Return recordID metadata for record.
     *
     * @return string
     */
    abstract public function getRecordId ();

    /**
     * Set recordID metadata for record.
     *
     * @param string $recordId
     */
    abstract public function setRecordId ($recordId);
};
