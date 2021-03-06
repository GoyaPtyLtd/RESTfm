<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2017 Goya Pty Ltd.
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

namespace RESTfm\Format;

use RESTfm\FormatInterface;
use RESTfm\Message\Message;

class FormatTxt implements FormatInterface {

    // --- Interface Implementation --- //

    /**
     * Parse the provided data string into the provided \RESTfm\Message\Message
     * implementation object.
     *
     * @param \RESTfm\Message\Message $restfmMessage
     * @param string $data
     */
    public function parse (Message $restfmMessage, $data) {
        throw new \RESTfm\ResponseException('No input parser available for txt format.', 500);
    }

    /**
     * Write the provided \RESTfm\Message\Message object into a formatted string.
     *
     * @codeCoverageIgnore Not a testable unit.
     *
     * @param \RESTfm\Message\Message $restfmMessage
     *
     * @return string
     */
    public function write (Message $restfmMessage) {

        // Extensions like xdebug will reformat var_dump output if
        // html_errors is set.
        $html_errors = ini_get('html_errors');
        if ($html_errors == TRUE) {
            ini_set('html_errors', FALSE);
        }

        // Capture var_dump output via output buffer routines.
        ob_start();
        var_dump($restfmMessage->exportArray());
        $str = ob_get_contents();
        ob_end_clean();

        // Restore the html_errors ini variable.
        ini_set('html_errors', $html_errors);

        return $str;
    }

}
