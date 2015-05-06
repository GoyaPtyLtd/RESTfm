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

require_once 'QueryString.php';
require_once 'RFMfixFM01.php';
require_once 'RFMfixFM02.php';

/**
 * Extends QueryString to include additional encoding.
 */
class RESTfmQueryString extends QueryString {

    /**
     * Allowed encoding types.
     */
    const   none        = 0,
            RFMfixFM01  = 1,
            RFMfixFM02  = 2;

    /**
     * @var integer encoding for this instance.
     *  Set automatically if initialised from $_SERVER['QUERY_STRING'].
     */
    protected $_encoding = NULL;

    /**
     * Override fromServer() to allow for RFMfixFM01 and RFMfixFM02 flags
     * to work around FM's 'Insert From URL' bugs.
     *
     * This is called by base class constructor.
     *
     * Currently required for FM11, FM12 and FMGo12, FM13 and FMGo13
     *
     * Initialise from $_SERVER['QUERY_STRING']
     */
    public function fromServer() {
        // If an encoding has not yet been set.
        if ($this->_encoding === NULL) {
            // Analyse query string for RFMfixFM01 flag.
            if (strpos($_SERVER['QUERY_STRING'], 'RFMfixFM01') !== FALSE) {
                $this->_encoding = self::RFMfixFM01;
            }

            // Analyse query string for RFMfixFM02 flag.
            if (strpos($_SERVER['QUERY_STRING'], 'RFMfixFM02') !== FALSE) {
                $this->_encoding = self::RFMfixFM02;
            }
        }

        $this->parse_str($_SERVER['QUERY_STRING'], $this->_data);
    }

    /**
     * Explicitly set encoding.
     *
     * @var integer $encoding
     *  Must be from allowed encoding types constant in this class.
     */
    public function setEncoding ($encoding) {
        $this->_encoding = $encoding;
    }

    /**
     * Returns current encoding.
     */
    public function getEncoding () {
        return $this->_encoding;
    }

    /**
     * Override _urldecode() to allow for additional encoding.
     *
     * This is called by parse_str() in base class.
     */
    protected function _urldecode($str) {
        switch ($this->_encoding) {
            case self::RFMfixFM01:
                return RFMfixFM01::postDecode(urldecode($str));
            case self::RFMfixFM02:
                return RFMfixFM02::postDecode(urldecode($str));
            default:
                return urldecode($str);
        }
    }

    /**
     * Special case build() for producing a compatible encoding. This method
     * is for compatibilty testing and is otherwise not expected to be used.
     *
     * Build and return a HTTP query string.
     *
     * @param integer $encoding
     *  1 or 2 (for RFMfixFM01 or RFMfixFM02 respectively).
     *
     * @param boolean $prefixQuestionMark
     *  (optional) Default TRUE. If set TRUE the question mark required by
     *  a HTTP URL to delineate parameters is prefixed. Only if the string
     *  is otherwise non-empty.
     *
     * @return string
     *  HTTP encoded query string.
     */
    public function buildEncoded($prefixQuestionMark = TRUE) {
        $dataEncoded = array();
        foreach ($this->_data as $key => $val) {
            switch ($this->_encoding) {
                case self::RFMfixFM01:
                    $key = rawurlencode(RFMfixFM01::preEncode($key));
                    $val = rawurlencode(RFMfixFM01::preEncode($val));
                    break;
                case self::RFMfixFM02:
                    $key = rawurlencode(RFMfixFM02::preEncode($key));
                    $val = rawurlencode(RFMfixFM02::preEncode($val));
                    break;
            }
            $dataEncoded[] = $key . '=' . $val;
        }

        $qstring = implode('&', $dataEncoded);
        if ($prefixQuestionMark && !empty($qstring)) {
            $qstring = '?'.$qstring;
        }
        return $qstring;
    }

};
