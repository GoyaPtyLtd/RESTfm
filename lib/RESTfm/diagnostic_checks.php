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

// Expensive to parse the server query string every time if diagnostics
// is left enabled. So we do a cheap peek into the URL instead.

// The RFMversion query string simply returns the RESTfm version
// and nothing else.
if (strpos($_SERVER['QUERY_STRING'], 'RFMversion') !== FALSE) {
    throw new ResponseException(Version::getVersion(), Response::OK);
}

// The RFMcheckFMAPI query string simply returns FM API version
// after trying to require the FileMaker API php file.
if (strpos($_SERVER['QUERY_STRING'], 'RFMcheckFMAPI') !== FALSE) {
    require_once 'FileMaker.php';

    $fileMakerReflection = new ReflectionClass('FileMaker');

    $s = '';
    $s .= 'FileMaker API found at path      : ' . $fileMakerReflection->getFileName() . "\n";

    $s .= 'Compatible with FileMaker Server : ';
    if ($fileMakerReflection->hasMethod('getContainerDataURL')) {
        $s .= '12,13';
    } else {
        $s .= '11';     // RESTfm only supports FMS versions down to 11.
    }

    throw new ResponseException($s, Response::OK);
}
