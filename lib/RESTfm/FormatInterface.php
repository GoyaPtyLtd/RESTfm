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

interface FormatInterface {

    /**
     * Parse the provided data string into the provided RESTfmMessage
     * implementation object.
     *
     * @param RESTfmMessage $restfmMessage
     * @param string $data
     */
    public function parse (RESTfmMessage $restfmMessage, $data);

    /**
     * Write the provided RESTfmMessage object into a formatted string.
     *
     * @param RESTfmMessage $restfmMessage
     *
     * @return string
     */
    public function write (RESTfmMessage $restfmMessage);

};
