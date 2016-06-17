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
 * An extension of RESTfmMessageRowInterface with additional record related
 * metadata access methods.
 */
class RESTfmMessageRecord extends RESTfmMessageRow implements RESTfmMessageRecordInterface {

    protected $_meta = array();

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
    public function setRecordId ($recordID) {
        $this->_meta['recordID'] = $recordID;
    }

    /**
     * RESTfmMessage internal function.
     * Return a reference to the internal _meta array.
     *
     * @param arrayref _meta array.
     */
    public function &_getMetaReference () {
        return $this->_meta;
    }
};
