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
 * FileMaker 13 POST encoding requirements.
 */
class RFMfixFM02 {

    /**
     * Post-decode provided string (after normal urldecode of http form
     * parameters) to allow for FileMaker 13 httppost: (Insert From URL)
     * requirements. FileMaker appears to handle the urlencode itself, but
     * avoids encoding '=' and '&', as only a single string containing all
     * http form parameters may be passed to the function.
     *
     * @param string $s
     *  Input string to be post-decoded after passing to urldecode.
     *
     * @returns string
     */
    public static function postDecode ($s) {

        $s = str_replace('%3D', '=', $s);
        $s = str_replace('%26', '&', $s);

        // As very last step, decode '%' symbols.
        $s = str_replace('%25', '%', $s);

        return ($s);
    }

    /**
     * Pre-encode provided string as would be required by FileMaker 13
     * when using httppost: (Insert From URL). FileMaker appears to handle
     * the urlencode itself, but avoids encoding '=' and '&', as only a single
     * string containing all http form parameters may be passed to the
     * function.
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

        $s = str_replace('=', '%3D', $s);
        $s = str_replace('&', '%26', $s);

        return ($s);
    }

};
