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

namespace RESTfm\Message;

 /**
  * An array-like object for a single row of fieldName/value pairs.
  */
abstract class RowAbstract implements \ArrayAccess,
                                      \IteratorAggregate,
                                      \Countable {

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
    abstract public function __construct ($assocArray = NULL);

    /**
     * @param array $assocArray
     *  Optional array to initalise row data.
     */
    abstract public function setData ($assocArray);

    // -- ArrayAccess implementation. -- //

    abstract public function offsetExists ($offset);

    abstract public function offsetGet ($offset);

    abstract public function offsetSet ($offset, $value);

    abstract public function offsetUnset ($offset);

    // -- IteratorAggregate implementation. -- //

    abstract public function getIterator ();

    // -- Countable implementation. -- //

    abstract public function count ();

};
