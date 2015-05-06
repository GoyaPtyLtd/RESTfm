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

class FormatTxt extends FormatAbstract {

    // --- Interface Implementation --- //

    public function parse (RESTfmDataAbstract $restfmData, $data) {
        throw RESTfmResponseException('No input parser available for txt format.', 500);
    }

    public function write (RESTfmDataAbstract $restfmData) {
        $tables = $this->_collate($restfmData);

        // Extensions like xdebug will reformat var_dump output if
        // html_errors is set.
        $html_errors = ini_get('html_errors');
        if ($html_errors == TRUE) {
            ini_set('html_errors', FALSE);
        }

        // Capture var_dump output via output buffer routines.
        ob_start();
        var_dump($tables);
        $str = ob_get_contents();
        ob_end_clean();

        // Restore the html_errors ini variable.
        ini_set('html_errors', $html_errors);

        return $str;
    }

}
