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

/**
 * HTTP Query String manipulation class
 */
class QueryString {

    /**
     * @var array QueryString data.
     */
    protected $_data = array();

    /**
     * New QueryString object.
     *
     * @param boolean $setFromServer
     *  (optional) Default FALSE. If set TRUE object is initialised
     *  from $_SERVER['QUERY_STRING'].
     */
    public function __construct($setFromServer = FALSE) {
        if ($setFromServer) {
            $this->fromServer();
        }
    }

    /**
     * Initialise from $_SERVER['QUERY_STRING']
     */
    public function fromServer() {
        $this->parse_str($_SERVER['QUERY_STRING'], $this->_data);
    }

    /**
     * Set $_SERVER['QUERY_STRING'] from QueryString
     */
    public function toServer() {
        $_SERVER['QUERY_STRING'] = $this->build(FALSE);
    }

    /**
     * PHP's parse_str converts dots and spaces to underscores, this one
     * doesn't.
     *
     * @param String $str
     *  Input query string to parse.
     * @param Array &$arr
     *  Array reference for parsed results.
     */
    public function parse_str($str, &$arr) {
        if (empty($str)) {
            return;
        }
        $pairs = explode('&', $str);
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue;
            }
            $pair_array = explode('=', $pair);
            $k = $this->_urldecode($pair_array[0]);
            if (isset($pair_array[1])) {
                $arr[$k] = $this->_urldecode($pair_array[1]);
            } else {
                $arr[$k] = '';
            }
        }
    }

    /**
     * Easy to override urldecode function for derived classes.
     */
    protected function _urldecode ($str) {
        return urldecode($str);
    }

    /**
     * Build and return a HTTP query string.
     *
     * @param boolean $prefixQuestionMark
     *  (optional) Default TRUE. If set TRUE the question mark required by
     *  a HTTP URL to delineate parameters is prefixed. Only if the string
     *  is otherwise non-empty.
     *
     * @return string
     *  HTTP encoded query string.
     */
    public function build($prefixQuestionMark = TRUE) {
        $qstring = http_build_query($this->_data);
        if ($prefixQuestionMark && !empty($qstring)) {
            $qstring = '?'.$qstring;
        }
        return $qstring;
    }

    /**
     * Return the value of a QueryString data key, equivalent to $_GET[$key]
     *
     * @param string $key
     *  QueryString key.
     *
     * @return string
     *  Value of $key in query string or NULL if non-existent $key.
     */
    public function __get($key) {
        if (isset($this->_data[$key])) {
            return $this->_data[$key];
        }
        return NULL;
    }

    /**
     * Check QueryString data key is set.
     *
     * @param string $key
     *  QueryString key.
     */
    public function __isset($key) {
        return isset($this->_data[$key]);
    }

    /**
     * Set QueryString data key (and value).
     *
     * @param string $key
     *  QueryString key.
     * @param string $value
     *  If set NULL QueryString key is deleted/unset.
     */
    public function __set($key, $value) {
        if ($value == NULL) {
            unset($this->_data[$key]);
        } else {
            $this->_data[$key] = $value;
        }
    }

    /**
     * Unset QueryString data key.
     *
     * @param string $key
     *  QueryString key.
     */
    public function __unset($key) {
        unset($this->_data[$key]);
    }

    /**
     * Return QueryString data keys matching provided Perl regular expression.
     *
     * @param string $pattern
     *  Perl regular expression.
     *
     * @return array
     *  An associative array of matched keys and their values.
     */
    public function getRegex($pattern) {
        $return = array();
        foreach ($this->_data as $key => $value) {
            if (preg_match($pattern, $key)) {
                $return[$key] = $value;
            }
        }
        return $return;
    }

    /**
     * Set QueryString data from provided array.
     *
     * @param array $array
     *  Associative array of query data.
     */
    public function setAll($array) {
        $this->_data = $array;
    }

    /**
     * Unset QueryString data keys matching provided Perl regular expression.
     *
     * @param string $pattern
     *  Perl regular expression.
     */
    public function unsetRegex($pattern) {
        foreach (array_keys($this->_data) as $key) {
            if (preg_match($pattern, $key)) {
                unset($this->_data[$key]);
            }
        }
    }

};
