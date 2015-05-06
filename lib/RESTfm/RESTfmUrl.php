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
 * Static class provides RESTfm specific urlencode/urldecode functions to
 * take into account RFMfixFM01 and RFMfixFM02 flags.
 *
 * RESTfm uses rawurlencode() for RFC 3986 compliance, but uses urldecode() to
 * remain compatible with systems that encode spaces as '+'. This combination
 * works well for all cases
 */
class RESTfmUrl {

    /**
     * Allowed encoding types.
     */
    const   none        = 0,
            RFMfixFM01  = 1,
            RFMfixFM02  = 2;

    /**
     * @var integer encoding mode. Default: none.
     */
    protected static $_encoding = self::none;

    // -- Public --

    /**
     * URL-encodes string.
     *
     * @param string $str
     *
     * @return string
     */
    public static function encode ($str) {
        switch (self::$_encoding) {
            case self::RFMfixFM01:
                return rawurlencode(RFMfixFM01::preEncode($str));
            case self::RFMfixFM02:
                return rawurlencode(RFMfixFM02::preEncode($str));
            default:
                return rawurlencode($str);
        }
    }

    /**
     * Decodes URL-encoded string.
     *
     * @param string $str
     *
     * @return string
     */
    public static function decode ($str) {
        switch (self::$_encoding) {
            case self::RFMfixFM01:
                return RFMfixFM01::postDecode(urldecode($str));
            case self::RFMfixFM02:
                return RFMfixFM02::postDecode(urldecode($str));
            default:
                return urldecode($str);
        }
    }

    /**
     * Explicitly set encoding.
     *
     * @var integer $encoding
     *  Must be from allowed encoding types constant in this class.
     */
    public static function setEncoding ($encoding) {
        self::$_encoding = $encoding;
    }

    /**
     * Returns current encoding.
     */
    public static function getEncoding () {
        return self::$_encoding;
    }

};
