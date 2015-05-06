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
 * @file
 *  Initial setup and diagnostic report.
 */

require_once('lib/RESTfm/Diagnostics.php');
require_once('lib/RESTfm/RESTfmConfig.php');
require_once('lib/RESTfm/Version.php');

$diagnostics = new Diagnostics();
$diagnostics->setCallingFilename('report.php');
$diagnostics->setDocumentRoot(dirname($_SERVER['SCRIPT_FILENAME']));

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
    <head>
        <meta http-equiv="Content-type" content="text/html; charset=utf-8">
        <title>RESTfm report</title>
        <style type="text/css">
            body {
                font-family: Lucida Grande, Lucida Sans, Arial, sans-serif;
                font-size: 62.5%;
            }
            #logo {
                float: right;
                font-size: 1.0em;
            }
            #logo > a {
                display: block;
            }
            #logo > span {
                float: right;
                margin-top: -7px;
            }
            div {
                font-size: 1.3em;
            }
            table {
                border: 1px solid #CCCCCC;
                border-collapse: collapse;
            }
            tr { border-bottom: 1px solid #CCCCCC }
            td {
                border-right: 1px solid #CCCCCC;
                padding: 6px;
                vertical-align: top;
            }
            pre {
                overflow: auto;
                margin: 0px;
            }
            .OK { background-color: #E5FFE2 }
            .INFO { background-color: #D3EDFF }
            .WARN { background-color: #FFFCE5 }
            .ERROR { background-color: #FFD2D6 }
        </style>
    </head>
    <body>
<?php if (RESTfmConfig::getVar('settings', 'diagnostics') === TRUE): // Diagnostics enabled. ?>
        <div id="logo">
            <a target="_blank" href="http://www.restfm.com"><img width="106" height="36" src="css/RESTfm.logo.png" alt="RESTfm logo"></a>
            <span><?php echo Version::getRelease() ?></span>
        </div>
        <h2>RESTfm report</h2>
<?php
        $diagnostics->run();
        if ($diagnostics->hasErrors) {
            echo '<h3>RESTfm is not working. Errors have been detected.</h3>' . "\n";
            echo '<h3>Start by correcting the topmost error first, and reloading the page each time.</h3>' . "\n";
        } else {
            echo '<h3>RESTfm is working! Click <a href="' . RESTfmConfig::getVar('settings', 'baseURI') . '">here</a> to start browsing with RESTfm.</h3>' . "\n";
        }
            $report = $diagnostics->getReport();
            echo $report;
?>
        <br>
        <div>
        Copy and Paste this text report when contacting support:<br>
<textarea rows="10" cols="100" readonly="readonly" id="text_report">
<?php
                $report->setFormat('text');
                echo $report;
?>
</textarea>
        </div>
<?php else: // Diagnostics disabled. ?>
        <div>
            Diagnostics disabled.
        </div>
<?php endif; // Diagnostics. ?>
    </body>
</html>
