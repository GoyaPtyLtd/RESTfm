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

// The RFMcheckFMAPI query string returns the determined FMS PHP API version
// after trying to require the FileMaker API php file.
if (strpos($_SERVER['QUERY_STRING'], 'RFMcheckFMAPI') !== FALSE) {
    require_once 'FileMaker.php';

    // Mapping of known FMS PHP API files, their md5, and therefore their
    // version. Each file is relative to the FMS PHP API directory.
    // Format is:
    // '<filename>' => '<md5sum>:<version>'
    // Note: First top-down match is taken as the version number.
    // Note: Files are carefully selected to capture changes from the
    //       version preceding it.
    $fmsFileMap = array (
        'FileMaker/Error/sv.php' => '82ac207c77fb95ead1d9fbdcd49c28ff:14, 15',
        'FileMaker/Implementation/Command/EditImpl.php' => 'e55064465260f2a4e1c0049abc77e90d:13',
        'FileMaker/Implementation/FileMakerImpl.php' => '344a84eafa71167103dbfa3927f3d13e:12',
        'FileMaker/Implementation/FileMakerImpl.php' => '5a526472505610de33affefc5df92f6a:11',
    );

    $fileMakerReflection = new ReflectionClass('FileMaker');

    $s = '';
    $s .= 'Found at path : ' . $fileMakerReflection->getFileName() . "\n";

    $dirname = dirname($fileMakerReflection->getFileName());
    $version = 'Unknown';
    foreach ($fmsFileMap as $filename => $md5sumAndVersion) {
        list ($md5sum, $versionMatch) = explode(':', $md5sumAndVersion);
        $fqpName = $dirname . DIRECTORY_SEPARATOR . $filename;
        //echo "Checking: $fqpName, " . md5_file($fqpName) . "\n";
        if (md5_file($fqpName) === $md5sum) {
            $version = $versionMatch;
            break;
        }
    }

    $s .= 'Version       : ' . $version;

    throw new ResponseException($s, Response::OK);
}
