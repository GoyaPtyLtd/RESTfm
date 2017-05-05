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

namespace RESTfm;

interface FormatInterface {

    /**
     * Parse the provided data string into the provided \RESTfm\Message\Message
     * implementation object.
     *
     * @param \RESTfm\Message\Message $restfmMessage
     * @param string $data
     */
    public function parse (\RESTfm\Message\Message $restfmMessage, $data);

    /**
     * Write the provided \RESTfm\Message\Message object into a formatted string.
     *
     * @param \RESTfm\Message\Message $restfmMessage
     *
     * @return string
     */
    public function write (\RESTfm\Message\Message $restfmMessage);

};
