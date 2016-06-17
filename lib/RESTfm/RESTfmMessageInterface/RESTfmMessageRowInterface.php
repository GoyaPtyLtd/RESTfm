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
  * A generic row interface for fieldName/value pairs.
  */
interface RESTfmMessageRowInterface {

    /**
     * Get associative array of all fieldName/value pairs.
     *
     * @return array of data.
     */
    public function getData ();

    /**
     * Set multiple fieldName/value pairs from associative array.
     *
     * @param array $assocArray
     */
    public function setData ($assocArray);

    /**
     * Get only specified fieldName.
     *
     * @param string $fieldName
     *
     * @return mixed
     */
    public function getField ($fieldName);

    /**
     * Set only specified fieldName.
     *
     * @param string $fieldName
     * @param mixed $fieldValue
     */
    public function setField ($fieldName, $fieldValue);

    /**
     * Unset/delete specified fieldName.
     *
     * @param string $fieldName
     */
    public function unsetField ($fieldName);
};
