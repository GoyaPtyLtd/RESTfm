<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2018 Goya Pty Ltd.
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
use RESTfm\Message\Record;

class FormatChargify implements FormatInterface {

    // --- Interface Implementation --- //

    public function parse (Message $restfmMessage, $data) {
        // $data is URL encoded key => value pairs as in a HTTP POST body or
        // HTTP GET query string. Note that 'payload' is multidimensional.
        //
        // e.g. id=350989458&event=test&payload[chargify]=testing

        $a = array();

        // Note this is PHP's parse_str(), and it mangles names by converting
        // dots and spaces into underscores.
        parse_str($data, $a);

        // We expect a layout with at least these three fields.
        $recordData = array(
            'id'        => $a['id'],
            'event'     => $a['event'],
            'payload'   => json_encode($a['payload']),
        );

        $restfmMessage->addRecord(new Record( NULL, NULL, $recordData ));
    }

    /**
     * @codeCoverageIgnore Not a testable unit.
     */
    public function write (Message $restfmMessage) {
        return;
    }

    // -- Protected -- //

}
