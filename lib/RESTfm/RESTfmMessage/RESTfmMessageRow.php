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
  * An array-like object for a single row of fieldName/value pairs.
  */
class RESTfmMessageRow extends RESTfmMessageRowAbstract {

    /**
     * @var array of fieldName/value pairs.
     */
    protected $_data = array();

    /**
     * An array-like object for a single row of fieldName/value pairs.
     *
     * Works as expected with foreach(), but must cast to (array) for PHP
     * functions like array_keys().
     *
     * Typical array assignments ($a['key'] = 'val') are fine.
     *
     * @param array $assocArray
     *  Optional array to initalise row data.
     */
    public function __construct ($assocArray = NULL) {
        if ($assocArray !== NULL) { $this->_data = $assocArray; }
     }

    /**
     * @param array $assocArray
     *  Optional array to initalise row data.
     */
    public function setData ($assocArray) {
        $this->_data = $assocArray;
    }

    /**
     * RESTfmMessage internal function.
     * Return a reference to the internal _data array.
     *
     * @return arrayref _data array.
     */
    public function &_getDataReference () {
        return $this->_data;
    }


    // -- ArrayAccess implementation. -- //

    public function offsetExists ($offset) {
        return (isset($this->_data[$offset]));
    }

    public function offsetGet ($offset) {
        if (isset($this->_data[$offset])) {
            return $this->_data[$offset];
        }
        return FALSE;
    }

    public function offsetSet ($offset, $value) {
        $this->_data[$offset] = $value;
    }

    public function offsetUnset ($offset) {
        unset($this->_data[$offset]);
    }

    // -- IteratorAggregate implementation. -- //

    public function getIterator () {
        return new ArrayIterator($this->_data);
    }

    // -- Countable implementation. -- //

    public function count () {
        return count($this->_data);
    }

};
