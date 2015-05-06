<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2015 Goya Pty Ltd.
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

require_once 'RESTfmRequest.php';

/**
 * RESTfmParameters
 *
 * Class encapsulating all parameters applicable to a RESTfm request.
 * Parameters are populated from multiple sources, in the following lowest
 * to heighest priority:
 *  3) Any RFM* parameters inside the 'info' section of the submitted
 *     HTTP data.
 *  2) Any RFM* parameters in application/x-www-form-urlencoded or
 *     multipart/form-data.
 *  1) Any RFM* parameters in the URI query string.
 */
class RESTfmParameters implements Iterator {

    /**
     * @var array $_parameters
     *  Associative array of key => value pairs.
     */
    protected $_parameters = array();

    /**
     * Return keys matching provided Perl regular expression.
     *
     * @param string $pattern
     *  Perl regular expression.
     *
     * @return array
     *  An associative array of matched keys and their values.
     */
    public function getRegex($pattern) {
        $return = array();
        foreach ($this->_parameters as $key => $value) {
            if (preg_match($pattern, $key)) {
                $return[$key] = $value;
            }
        }
        return $return;
    }

    /**
     * Merge provided $array into parameters, overwriting any elements that
     * already exist. i.e. New $array elements are higher priority than
     * existing elements.
     *
     * @param array $array
     *  Associative array of key => value pairs to merge. It is OK for
     *  a key to have a NULL value.
     */
    public function merge ($array) {
        $this->_parameters = array_merge($this->_parameters, $array);
    }

    // -- Magic accessor methods. --

    /**
     * __get magic method.
     *
     * @param string $key
     *  Parameter key.
     *
     * @return string
     *  Value of $key in query string or NULL if non-existent $key.
     */
    public function __get ($key) {
        if (isset($this->_parameters[$key])) {
            return $this->_parameters[$key];
        }
        return NULL;
    }

    /**
     * __isset magic method.
     *
     * @param string $key
     *  Parameter key.
     */
    public function __isset ($key) {
        return isset($this->_parameters[$key]);
    }

    /**
     * __set magic method.
     *
     * @param string $key
     *  Parameter key.
     * @param string $value
     *  Parameter value.
     */
    public function __set ($key, $value) {
        $this->_parameters[$key] = $value;
    }

    /**
     * __unset magic method.
     *
     * @param string $key
     *  Parameter key.
     */
    public function __unset ($key) {
        unset($this->_parameters[$key]);
    }

    /**
     * __toString magic method.
     */
    public function __toString () {
        $s = '';
        foreach ($this->_parameters as $key => $value) {
            $s .= $key . '="' . addslashes($value) . '"' . "\n";
        }
        return $s;
    }

    // --- Iterator interface implementation --- //

    public function current() {
        return current($this->_parameters);
    }

    public function key() {
        return key($this->_parameters);
    }

    public function next() {
        return next($this->_parameters);
    }

    public function rewind() {
        return reset($this->_parameters);
    }

    public function valid() {
        return key($this->_parameters) !== NULL;
    }

};
