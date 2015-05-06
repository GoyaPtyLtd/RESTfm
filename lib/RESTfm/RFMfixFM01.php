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
 * Static class to handle the pre and post encoding step to work around
 * FileMaker 12 Insert From URL encoding requirements.
 */
class RFMfixFM01 {

    /**
     * @var array Bad character array.
     *
     * Note: Originally we throught 0x80 to 0xFF were needed as well. FM
     *       appears to be handling chars > 127 correctly as Unicode.
     */
    protected static $_badChars = array (
        0x00, 0x21, 0x23, 0x24, 0x26, 0x27, 0x28, 0x29, 0x2A, 0x2B, 0x2C, 0x2F,
        0x3A, 0x3B, 0x3D, 0x3F, 0x40, 0x5B, 0x5D,
    );

    /**
     * Post-decode provided string (after normal urldecode of http form
     * parameters) to allow for FileMaker 12 http: (Insert From URL)
     * requirements.
     *
     * @param string $s
     *  Input string to be post-decoded after passing to urldecode.
     *
     * @returns string
     */
    public static function postDecode ($s) {

        // Decode all encoded bad characters.
        foreach (self::$_badChars as $i) {
            $s = str_replace(sprintf('%%%02X', $i), chr($i), $s);
        }

        // As very last step, decode '%' symbols.
        $s = str_replace('%25', '%', $s);

        return($s);
    }

    /**
     * Pre-encode provided string as would be required by FileMaker 12
     * when using http: (Insert From URL).
     *
     * This method exists only for testing and is not needed by RESTfm.
     *
     * @param string $s
     *  Input string to be pre-encoded before passing to rawurlencode.
     *
     * @returns string
     */
    public static function preEncode ($s) {

        // As very first step, encode '%' symbols.
        $s = str_replace('%', '%25', $s);

        // Encode all remaining bad chars.
        foreach (self::$_badChars as $i) {
            $s = str_replace(chr($i), sprintf('%%%02X', $i), $s);
        }

        return ($s);
    }

};
