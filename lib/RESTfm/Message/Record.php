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

use RESTfm\MessageInterface\RecordAbstract;

/**
 * An extension of RESTfm\Message\Row (array-like) with additional record
 * related metadata access methods.
 */
class Record extends RecordAbstract {

    protected $_meta = array();

    /**
     * A record object containing recordID, href, and fieldName/value pairs in
     * Message.
     *
     * @param string $recordId
     *  Optional recordID.
     * @param string $href
     *  Optional href.
     * @param array $assocArray
     *  Optional array to initalise record data.
     */
    public function __construct ($recordId = NULL, $href = NULL, $assocArray = NULL) {
        if ($recordId !== NULL) { $this->_meta['recordID'] = $recordId; }
        if ($href !== NULL) { $this->_meta['href'] = $href; }
        if ($assocArray !== NULL) { parent::__construct($assocArray); }
    }

    /**
     * Return href metadata for record.
     *
     * @return string
     */
    public function getHref () {
        if (isset($this->_meta['href'])) return $this->_meta['href'];
    }

    /**
     * Set href metadata for record.
     *
     * @param string $href
     */
    public function setHref ($href) {
        $this->_meta['href'] = $href;
    }

    /**
     * Return recordID metadata for record.
     *
     * @return string
     */
    public function getRecordId () {
        if (isset($this->_meta['recordID'])) return $this->_meta['recordID'];
    }

    /**
     * Set recordID metadata for record.
     *
     * @param string $recordId
     */
    public function setRecordId ($recordId) {
        $this->_meta['recordID'] = $recordId;
    }

    /**
     * RESTfm\Message internal function.
     * Return a reference to the internal _meta array.
     *
     * @param arrayref _meta array.
     */
    public function &_getMetaReference () {
        return $this->_meta;
    }

};
