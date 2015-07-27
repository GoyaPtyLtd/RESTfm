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

require_once 'RESTfmConfig.php';
require_once 'RESTfmLic.php';

/**
 * Diagnostics class.
 */
class Diagnostics {

    // -- Private properties -- //

    /**
     * @var
     *  List of tests.
     */
    private $_tests = array (
        'version',
        'phpVersion',
        'phpPdoDrivers',
        'webServerVersion',
        'hostServerVersion',
        'hostSystemDate',
        'documentRoot',
        //'licence',
        'baseURI',
        'webserverRedirect',
        'filemakerAPI',
        'filemakerConnect',
        'sslEnforced',
        'sslServer',
        'sslWebserverRedirect',
        'xslExtension',
        );

    /**
     * @var
     *  Location of Diagnostics.php relative to RESTfm root.
     *  This is used for determining the RESTfm root on the filesystem.
     */
    private $_thisRelativePath = 'lib/RESTfm/Diagnostics.php';

    /**
     * @var
     *  RESTfm document root.
     */
    private $_RESTfmDocumentRoot = NULL;

    /**
     * @var
     *  Filename of calling script relative to the RESTfm base URI.
     */
    private $_callingFilename = NULL;

    private $_report = NULL;

    // -- Public properties and methods -- //
    /**
     * @var
     *  Set TRUE when a report contains warnings.
     */
    public $hasWarnings = FALSE;

    /**
     * @var
     *  Set TRUE when a report contains errors.
     */
    public $hasErrors = FALSE;

    /**
     * Run diagnostic tests.
     */
    public function run() {
        $this->_report = new Report();

        foreach ($this->_tests as $test) {
            $reportItem = new ReportItem();
            call_user_func(array($this, 'test_'.$test), $reportItem);
            $this->_report->$test = $reportItem;

            if ($reportItem->status == ReportItem::WARN) {
                $this->hasWarnings = TRUE;
            } elseif ($reportItem->status == ReportItem::ERROR) {
                $this->hasErrors = TRUE;
            }
        }
    }

    /**
     * Inform diagnostics what the RESTfm document root is.
     */
    public function setDocumentRoot($documentRoot) {
        $this->_RESTfmDocumentRoot = $documentRoot;
    }

    /**
     * Inform diagnostics what the calling scripts filename is. This
     * allows diagnostics to check how it was called compared to
     * $_SERVER['REQUEST_URI'].
     *
     * @param string $filename
     *  Filename of calling script relative to the RESTfm base URI.
     */
    public function setCallingFilename($filename) {
        $this->_callingFilename = $filename;
    }

    /**
     * Return Diagnostic tests Report instance.
     */
    public function getReport() {
        return $this->_report;
    }

    // Test functions.

    public function test_version($reportItem) {
        $reportItem->name = 'RESTfm version';
        require_once 'Version.php';
        $reportItem->details = Version::getVersion();
    }

    public function test_phpVersion($reportItem) {
        $reportItem->name = 'PHP version';
        $reportItem->details = phpversion() . "\n";
        if (PHP_VERSION_ID < 50300) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .= "Minimum supported PHP version is: 5.3\n";
        }
    }

    public function test_phpPdoDrivers($reportItem) {
        $reportItem->name = 'PHP PDO drivers';

        if (! class_exists('PDO') || ! method_exists('PDO', 'getAvailableDrivers')) {
            $reportItem->details = "N/A";
            return;
        }

        $pdoDrivers = PDO::getAvailableDrivers();

        if (count($pdoDrivers) <= 0) {
            $reportItem->details = "none";
        } else {
            $reportItem->details = join(', ', $pdoDrivers);
        }
    }


    public function test_webServerVersion($reportItem) {
        $reportItem->name = 'Web Server version';
        $reportItem->details = $_SERVER['SERVER_SOFTWARE'] . "\n";

        // Check for IIS < 7
        if ($this->_isIIS() && $this->_isIIS() < 7.0) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .= 'Microsoft IIS less than version 7.0 is not supported.' . "\n";
        }
    }

    public function test_hostServerVersion($reportItem) {
        $reportItem->name = 'Host Server version';
        $reportItem->details =  "Operating System Name : " . php_uname('s') . "\n" .
                                "Host Name             : " . php_uname('n') . "\n" .
                                "Release Name          : " . php_uname('r') . "\n" .
                                "Version Information   : " . php_uname('v') . "\n" .
                                "Machine Type          : " . php_uname('m') . "\n";
    }

    public function test_hostSystemDate($reportItem) {
        $reportItem->name = 'Host Server date';
        // We suppress errors here, as we don't care if date.timezone is not
        // configured in php.ini, let PHP guess without raising a warning.
        $reportItem->details = @date('Y-m-d H:i:s P (T - e)', time());
    }

    public function test_documentRoot($reportItem) {
        $reportItem->name = 'Install location';
        $reportItem->details = $this->_RESTfmDocumentRoot . "\n";
    }

    public function test_licence($reportItem) {
        $reportItem->name = 'Licence';
        $lic = new RESTfmLic();

        if ($lic->exists() == TRUE) {
            $reportItem->details = $lic;
        } else {
            $reportItem->details .= 'No "Licence.php" file found in: ' . $this->_RESTfmDocumentRoot . "\n";
            $reportItem->status = ReportItem::ERROR;
            return;
        }

        if ($lic->integrity() === TRUE) {
            $reportItem->details .= "\n" . 'Integrity OK';
        } else {
            $reportItem->details .= "\n" . 'Integrity BAD';
            $reportItem->status = ReportItem::ERROR;
            return;
        }

        $validationResult = $lic->validate();
        if ($validationResult === TRUE) {
            $reportItem->details .= "\n" . 'Validation OK';
        } else {
            $reportItem->details .= "\n" . 'Validation BAD:' . "\n" . $validationResult;
            $reportItem->status = ReportItem::ERROR;
        }
    }

    public function test_baseURI($reportItem) {
        $configBaseURI = RESTfmConfig::getVar('settings', 'baseURI');
        $reportItem->name = 'baseURI (' . RESTfmConfig::CONFIG_INI . ')';

        $calculatedBaseURI = $this->_calculatedBaseURI();

        if ($calculatedBaseURI != $configBaseURI) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .= "\n* Does not match URI determined from web server: $calculatedBaseURI\n\n";
            $reportItem->details .= "Instructions:\n\n";
            $reportItem->details .= "- Edit " . RESTfmConfig::CONFIG_INI . " and update 'baseURI' to: $calculatedBaseURI\n\n";
        }

        if ($this->_isApache()) {
            $htaccess = @ file_get_contents('.htaccess');
            $matches = array();
            if ($htaccess === FALSE) {
                $reportItem->status = ReportItem::ERROR;
                $reportItem->details .= "\n* Unable to read .htaccess to check RewriteBase.\n\n";
                $reportItem->details .= "Instructions:\n\n";
                $reportItem->details .= "- Check that the .htaccess file from the RESTfm archive has been copied to: " . $this->_RESTfmDocumentRoot . "\n";
                $reportItem->details .= "  Note: the .htaccess file may be considered a \"hidden file\" by your file browser.\n\n";
                $reportItem->details .= "- Reload this page immediately after this one change to see a reduction in further instructions.\n\n";
            } elseif (preg_match('/^\s*RewriteBase\s+(.+?)\s*$/m', $htaccess, $matches)) {
                if ($matches[1] != $configBaseURI) {
                    $reportItem->status = ReportItem::ERROR;
                    $reportItem->details .= "\n* Does not match RewriteBase specified in .htaccess: " . $matches[1] . "\n\n";
                    $reportItem->details .= "Instructions:\n\n";
                    $reportItem->details .= "- Edit .htaccess and update 'RewriteBase' to: $calculatedBaseURI.\n\n";
                }
            } else {
                $reportItem->status = ReportItem::ERROR;
                $reportItem->details .= "\n* Unable to locate RewriteBase in .htaccess. Please contact Goya support: http://www.restfm.com/help\n\n";
            }
        }

        if ($reportItem->status == ReportItem::ERROR) {
            $prefix_details = '';
            if ( $calculatedBaseURI != $configBaseURI &&
                        strcasecmp($calculatedBaseURI, $configBaseURI) == 0) {
                $prefix_details = "\n* Case sensitivity fault, accessed through URI: $calculatedBaseURI\n\n";
                $prefix_details .= "Instructions:\n\n";
                $prefix_details .= "- RESTfm is case sensitive, where Apple's HFS and Windows' NTFS filesystems are not.\n";
                $prefix_details .= "  Please correct the URL in your browser, and try again.\n\n";
                $prefix_details .= "- Reload this page immediately after this one change to see a reduction in further instructions.\n\n";
            } elseif ($calculatedBaseURI != '/RESTfm') {
                // If the baseURI is not /RESTfm, then suggest it should be.
                $prefix_details = "\n* For ease of installation, URI should be: /RESTfm but was accessed as: $calculatedBaseURI\n\n";
                $prefix_details .= "Instructions:\n\n";
                $prefix_details .= "- To considerably simplify installation, it is *strongly* suggested the RESTfm install folder: $calculatedBaseURI\n";
                $prefix_details .= "  be changed to: /RESTfm\n\n";
                $prefix_details .= "- Reload this page immediately after this one change to see a reduction in further instructions.\n\n";
            }
            $reportItem->details = $prefix_details . $reportItem->details;
        }

        // Prefix the configured baseURI details to the very start.
        $reportItem->details = $configBaseURI . "\n" . $reportItem->details;
    }

    public function test_webserverRedirect($reportItem) {
        $reportItem->name = 'Web server redirect to RESTfm.php';

        $URL = $this->_calculatedRESTfmURL() . '/?RFMversion';
        if (RESTfmConfig::getVar('settings', 'SSLOnly') && ! $this->_isHTTPS()) {
            $URL = preg_replace('/^http:/', 'https:', $URL);
        }

        $reportItem->details .= '<a href="'. $URL . '">' . $URL . '</a>' . "\n";

        $ch = curl_init($URL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if (RESTfmConfig::getVar('settings', 'strictSSLCertsReport') === FALSE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RESTfm Diagnostics');
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .=  'cURL failed with error: ' . curl_errno($ch) . ': ' . curl_error($ch) . "\n";
            if (curl_errno($ch) == 60) {    // SSL certificate problem: self signed certificate
                $reportItem->details .= 'On development (not production) systems it is possible to disable this check' ."\n";
                $reportItem->details .= 'by setting "strictSSLCertsReport" to FALSE in ' . RESTfmConfig::CONFIG_INI ."\n";
            }
        } elseif ( strpos($result, 'RESTfm is not configured') ) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .= 'Redirection not working, index.html was returned instead.' . "\n";
            if ($this->_isApache()) {
                if ($this->_isDarwin()) {
                    $reportItem->details .= htmlspecialchars($this->_darwinAllowOverrideInstructions());
                } else {
                    $reportItem->details .= 'Check the Apache httpd configuration has \'AllowOverride All\' for the RESTfm directory.' . "\n";
                }
            }
        } elseif ( $result != Version::getVersion() ) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .=  'RESTfm failed to respond correctly: ' . $result . "\n";
        } else {
            $reportItem->details .= 'OK';
        }

        curl_close($ch);
    }

    public function test_filemakerAPI($reportItem) {
        $reportItem->name = 'FileMaker PHP API';

        $URL = $this->_calculatedRESTfmURL() . '/RESTfm.php?RFMcheckFMAPI';
        if (RESTfmConfig::getVar('settings', 'SSLOnly') && ! $this->_isHTTPS()) {
            $URL = preg_replace('/^http:/', 'https:', $URL);
        }

        $reportItem->details .= '<a href="'. $URL . '">' . $URL . '</a>' . "\n";

        $ch = curl_init($URL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if (RESTfmConfig::getVar('settings', 'strictSSLCertsReport') === FALSE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RESTfm Diagnostics');
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .=  'cURL failed with error: ' . curl_errno($ch) . ': ' . curl_error($ch) . "\n";
        } elseif (strpos($result, 'Found at path') === FALSE) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .=  'FileMaker PHP API not found in PHP include path.' . "\n";
        } else {
            $reportItem->details .= $result;
        }

        curl_close($ch);
    }

    public function test_filemakerConnect($reportItem) {
        $reportItem->name = 'FileMaker Server connection test';

        if ($this->_report->filemakerAPI->status != ReportItem::OK) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details = 'Cannot test, FileMaker PHP API not found.' . "\n";
            return;
        }

        $hostspec = RESTfmConfig::getVar('database', 'hostspec');
        $reportItem->details .= $hostspec . "\n";

        // Probe hostspec for fmi/xml/fmresultset.xml path using cURL, this
        // will verify that FileMaker Web Publishing Engine is really
        // configured and listening. Otherwise the second part of this test
        // using the FileMaker API can give a false positive as any webserver
        // listening can give a 404 error (which the FM API returns as
        // error 22, which may just be related to bad credentials !).
        $ch = curl_init($hostspec . '/fmi/xml/fmresultset.xml');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if (RESTfmConfig::getVar('settings', 'strictSSLCertsFMS') === FALSE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RESTfm Diagnostics');
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .=  'cURL failed with error: ' . curl_errno($ch) . ': ' . curl_error($ch) . "\n";
            if (curl_errno($ch) == 60) {    // SSL certificate problem: self signed certificate in certificate chain
                $reportItem->details .= 'On development (not production) systems it is possible to disable this check' ."\n";
                $reportItem->details .= 'by setting "strictSSLCertsFMS" to FALSE in ' . RESTfmConfig::CONFIG_INI ."\n";
            }
        } elseif (stripos($result, 'FileMaker') === FALSE) {
            $reportItem->status = ReportItem::ERROR;
            $reportItem->details .=  'FileMaker Web Publishing Engine not found at configured hostspec.' . "\n";
        }

        curl_close($ch);
        if ($reportItem->status == ReportItem::ERROR) { return; }

        $reportItem->details .= $result;

        // Now use the FileMaker API to test the connection.
        require_once 'init_paths.php';
        require_once 'FileMaker.php';

        $FM = new FileMaker();
        $FM->setProperty('hostspec', $hostspec);
        if (RESTfmConfig::getVar('settings', 'strictSSLCertsFMS') === FALSE) {
            $FM->setProperty('curlOptions', array(
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                                CURLOPT_SSL_VERIFYHOST => FALSE,
                                ));
        }

        $fileMakerResult = $FM->listDatabases();
        $unauthorised = FALSE;
        if (FileMaker::isError($fileMakerResult)) {
            // These response codes and why we use them are documented in:
            // RESTfm/FileMakerResponseException.php
            $fmCode = $fileMakerResult->getCode();
            $fmMessage = $fileMakerResult->getMessage();
            if ($fmCode == 22 && stripos($fmMessage, 'password') !== FALSE) {
                $unauthorised = TRUE;
            } elseif ($fmCode == 18 && stripos($fmMessage, 'account') !== FALSE) {
                $unauthorised = TRUE;
            } elseif ($fmCode == 9 && stripos($fmMessage, 'privileges') !== FALSE) {
                $unauthorised = TRUE;
            } else {
                $reportItem->status = ReportItem::ERROR;
                $reportItem->details .= 'FileMaker API returned error: ' . $fmCode . ': ' . $fmMessage . "\n";
                return;
            }
        }

        if ($unauthorised == TRUE) {
            $reportItem->details .= 'OK' . "\n";
        } else {
            $reportItem->status = ReportItem::WARN;
            $reportItem->details .= 'Connection is OK, but a warning applies:' . "\n";
            $reportItem->details .= 'FileMaker Server allowed the Guest Account to list databases.' . "\n";
            $reportItem->details .= 'The FileMaker Server Admin Console should be used to set Database Server -> Security to: ' . "\n";
            $reportItem->details .= "  'List only the databases each user is authorized to access'" . "\n";
        }
    }

    public function test_sslEnforced($reportItem) {
        $reportItem->name = 'SSL enforced (' . RESTfmConfig::CONFIG_INI . ')';

        if (RESTfmConfig::getVar('settings', 'SSLOnly') != TRUE) {
            $reportItem->status = ReportItem::WARN;
            $reportItem->details .= "SSLOnly not TRUE in " . RESTfmConfig::CONFIG_INI . ' configuration file.' . "\n";
            $reportItem->details .= 'SSL is highly recommended to protect data, usernames and passwords from eavesdropping.' . "\n";
        } else {
            $reportItem->details .= 'OK' . "\n";
        }
    }

    public function test_sslServer($reportItem) {
        $reportItem->name = 'SSL enabled on web server';

        // Increase error level if user has enforced SSL in config.
        $SSLfailureCode = ReportItem::WARN;
        if ($this->_report->sslEnforced->status == ReportItem::OK) {
            $SSLfailureCode = ReportItem::ERROR;
        }

        if ($this->_isHTTPS() && $this->_report->webserverRedirect->status == ReportItem::OK) {
            // Already working.
            $reportItem->details = "OK";
            $reportItem->status = ReportItem::NA;
            return;
        }

        $URL = 'https://' . $_SERVER['SERVER_NAME'];
        $reportItem->details .= '<a href="'. $URL . '">' . $URL . '</a>' . "\n";

        $ch = curl_init($URL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if (RESTfmConfig::getVar('settings', 'strictSSLCertsReport') === FALSE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RESTfm Diagnostics');
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $reportItem->status = $SSLfailureCode;
            $reportItem->details .=  'cURL failed with error: ' . curl_errno($ch) . ': ' . curl_error($ch) . "\n";
            if (curl_errno($ch) == 60) {    // SSL certificate problem: self signed certificate
                $reportItem->details .= 'On development (not production) systems it is possible to disable this check' ."\n";
                $reportItem->details .= 'by setting "strictSSLCertsReport" to FALSE in ' . RESTfmConfig::CONFIG_INI ."\n";
            }
        } else {
            $reportItem->details .= "OK" . "\n";
        }
        curl_close($ch);
    }

    public function test_sslWebserverRedirect($reportItem) {
        $reportItem->name = 'SSL redirect to RESTfm';

        // Increase error level if user has enforced SSL in config.
        $SSLfailureCode = ReportItem::WARN;
        if ($this->_report->sslEnforced->status == ReportItem::OK) {
            $SSLfailureCode = ReportItem::ERROR;
        }

        if ($this->_isHTTPS() && $this->_report->webserverRedirect->status == ReportItem::OK) {
            // Already working.
            $reportItem->details = "OK";
            $reportItem->status = ReportItem::NA;
            return;
        } elseif ($this->_report->sslServer->status != ReportItem::OK) {
            // No chance.
            $reportItem->details = "Not tested, SSL not enabled on web server.";
            $reportItem->status = $SSLfailureCode;
            return;
        }

        $URL = $this->_calculatedRESTfmURL() . '/?RFMversion';
        $URL = preg_replace('/^http:/', 'https:', $URL);

        $reportItem->details .= '<a href="'. $URL . '">' . $URL . '</a>' . "\n";

        $ch = curl_init($URL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if (RESTfmConfig::getVar('settings', 'strictSSLCertsReport') === FALSE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RESTfm Diagnostics');
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $reportItem->status = ReportItem::WARN;
            $reportItem->details .=  'cURL failed with error: ' . curl_errno($ch) . ': ' . curl_error($ch) . "\n";
        } elseif ( strpos($result, 'RESTfm is not configured') ) {
            $reportItem->status = $SSLfailureCode;
            $reportItem->details .= 'Redirection not working, index.html was returned instead.' . "\n";
            if ($this->_isApache()) {
                $reportItem->details .= 'Check the Apache httpd configuration has \'AllowOverride All\' for the RESTfm Directory,' . "\n";
                $reportItem->details .= 'may also be needed in the VirtualHost section for port 443.' . "\n";
            }
        } elseif (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404 && $this->_isDarwinFileMaker13()) {
            $reportItem->status = $SSLfailureCode;
            $reportItem->details .= htmlspecialchars($this->_darwinFMS13SSLAllowOverrideInstructions());
        } elseif ( $result != Version::getVersion() ) {
            $reportItem->status = $SSLfailureCode;
            $reportItem->details .=  'RESTfm failed to respond correctly: ' . $result . "\n";
        } else {
            $reportItem->details .= 'OK';
        }

        curl_close($ch);
    }

    public function test_xslExtension ($reportItem) {
        $reportItem->name = 'PHP XSL extension';
        if (extension_loaded('xsl')) {
            $reportItem->status = ReportItem::OK;
            $reportItem->details .= 'OK' . "\n";
            return;
        }

        $reportItem->status = ReportItem::ERROR;
        $reportItem->details .= 'Not Loaded. XSLT will not function.' . "\n";
        $reportItem->details .= 'Only RESTfm .simple, .xml, .json and .html formats are available.' . "\n\n";
        if ($this->_isIIS()) {
            $reportItem->details .= "Instructions:\n\n";
            $reportItem->details .= '- Edit the php.ini file: ' . php_ini_loaded_file() . "\n";
            $reportItem->details .= '- Uncomment (remove leading semicolon) from the line that reads: ;extension=php_xsl.dll' . "\n";
            $reportItem->details .= '- Save changes to php.ini file.' . "\n";
            $reportItem->details .= '- Restart IIS to apply the changes.' . "\n";
            $reportItem->details .= '- Reload this page.' . "\n";
        } else {
            $reportItem->details .= 'Check that your Operating System has PHP XSL/XML packages installed.' . "\n";
        }

    }

    // -- Private methods -- //

    /**
     * Returns the calculated base URI for the installed location of RESTfm.
     */
    private function _calculatedBaseURI() {
        $calculatedBaseURI = $_SERVER['REQUEST_URI'];
        // Delete everything after the calling filename.
        if ($this->_callingFilename !== NULL) {
            $fPos = strpos($calculatedBaseURI, $this->_callingFilename);
            if ($fPos !== FALSE) {
                $calculatedBaseURI = substr($calculatedBaseURI, 0, $fPos);
            }
            // Strip any trailing slashes.
            $calculatedBaseURI = rtrim($calculatedBaseURI, '/');
        } else {
            // Strip query strings.
            $qPos = strpos($calculatedBaseURI, '?');
            if ($qPos !== FALSE) {
                $calculatedBaseURI = substr($calculatedBaseURI, 0, $qPos);
            }
        }
        return($calculatedBaseURI);
    }

    /**
     * Returns Version number if Apache is the server. Returns FALSE
     * otherwise.
     */
    private function _isApache() {
        $matches = array();
        if (preg_match('/Apache\/(\d+\.\d+\.\d+)/', $_SERVER['SERVER_SOFTWARE'], $matches)) {
            return $matches[1];
        }
        return FALSE;
    }

    /**
     * Returns Version number if Microsoft IIS is the server. Returns FALSE
     * otherwise.
     */
    private function _isIIS() {
        $matches = array();
        if (preg_match('/IIS\/(\d+(\.\d+)?)/', $_SERVER['SERVER_SOFTWARE'], $matches)) {
            return $matches[1];
        }
        return FALSE;
    }

    /**
     * Returns TRUE if https was used to connect.
     */
    private function _isHTTPS() {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ||
                    $_SERVER['SERVER_PORT'] == 443) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Returns Release string if Darwin is the Operating System. Returns False
     * otherwise.
     */
    private function _isDarwin() {
        if (preg_match('/Darwin/i', php_uname('s'))) {
            return php_uname('r');
        }
        return FALSE;
    }

    /**
     * Returns TRUE if detected to be Darwin 10.7 (Lion) Server installation.
     */
    private function _isDarwin107Server() {
        if ($this->_isDarwin() === FALSE) { return FALSE; }

        $apacheLaunchConf = @ file_get_contents('/System/Library/LaunchDaemons/org.apache.httpd.plist');
        if ($apacheLaunchConf !== FALSE &&
                strstr($apacheLaunchConf, 'MACOSXSERVER') !== FALSE) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Returns TRUE if detected to be Darwin >= 10.8 (Mountain Lion/Mavericks)
     * Server installation.
     */
    private function _isDarwinGE108Server() {
        if ($this->_isDarwin() === FALSE) { return FALSE; }

        $apacheLaunchConf = @ file_get_contents('/System/Library/LaunchDaemons/org.apache.httpd.plist');
        if ($apacheLaunchConf !== FALSE &&
                strstr($apacheLaunchConf, '/Library/Server/Web/Config/apache2') !== FALSE) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Returns TRUE if detected to be FileMaker 13 on Darwin.
     */
    private function _isDarwinFileMaker13() {
        if ($this->_isDarwin() === FALSE) { return FALSE; }

        // FMS13 sets the environment variable HTTP_ROOT to an obvious path.
        if (getenv('HTTP_ROOT') === '/Library/FileMaker Server/HTTPServer') {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Returns the proper RESTfm URL as determined by the calculated base URI.
     */
    private function _calculatedRESTfmURL() {
        $URL = 'http://';
        if ($this->_isHTTPS()) {
            $URL = 'https://';
        }
        $URL .= $_SERVER['SERVER_NAME'] . $this->_calculatedBaseURI();
        return($URL);
    }

    /**
     * FileMaker Server 13 has taken control of Apache by default now.
     *
     * @return
     *  String of instructions to enable AllowOverride on FMS13 on Darwin
     *  (Apple OSX)
     */
    private function _darwinFMS13AllowOverrideInstructions() {

        $fms13ApacheConfDir = "/Library/FileMaker Server/HTTPServer/conf";
        if (version_compare($this->_isApache(), '2.4', '>=')) {
            $restfmApacheConf = "httpd-RESTfm.FMS13.Apache24.OSX.conf";
        } else {
            // Otherwise we will assume the older Apache 2.2
            $restfmApacheConf = "httpd-RESTfm.FMS13.Apache22.OSX.conf";
        }

        $s = "\nFileMaker Server 13 on Apple OSX instructions:\n\n";

        $s .= '- Install the httpd config file from the RESTfm package by typing the following in a terminal:' . "\n";
        $s .= '    sudo cp "'.$this->_RESTfmDocumentRoot.'/contrib/'.$restfmApacheConf.'" "'.$fms13ApacheConfDir.'/extra"' . "\n";

        if ($this->_RESTfmDocumentRoot !== '/Library/FileMaker Server/HTTPServer/htdocs/RESTfm'
                && $this->_RESTfmDocumentRoot !== '/Library/FileMaker Server/HTTPServer/htdocs/httpsRoot/RESTfm') {
            $s .= "\n";
            $s .= '- Edit "'.$fms13ApacheConfDir.'/extra/'.$restfmApacheConf.'" and replace:' . "\n";
            $s .= '    /Library/FileMaker Server/HTTPServer/htdocs/RESTfm' . "\n";
            $s .= '  with:' . "\n";
            $s .= '    ' . $this->_RESTfmDocumentRoot . "\n";
        }

        $s .= "\n";
        $s .= '- Edit "'.$fms13ApacheConfDir.'/httpd.conf" and append the following line to the end of the file:' . "\n";
        $s .= '    Include conf/extra/'.$restfmApacheConf . "\n";
        $s .= "\n";
        $s .= '- Stop the FileMaker service by typing the following in a terminal:' . "\n";
        $s .= '    sudo launchctl stop com.filemaker.fms' . "\n";
        $s .= "\n";
        $s .= '- Wait until the FileMaker service has completely stopped, by checking for output from the following command in a terminal:    ' . "\n";
        $s .= '  (No output means that the FileMaker service has stopped.)' . "\n";
        $s .= '    ps ax | grep -i filemaker | grep -v grep' . "\n";
        $s .= "\n";
        $s .= '- Start the FileMaker service by typing the following in a terminal:' . "\n";
        $s .= '    sudo launchctl start com.filemaker.fms' . "\n";
        $s .= "\n";
        $s .= '- Reload this page.' . "\n";

        $s .= "\n";

        return $s;
    }

    /**
     * FileMaker Server 13 has taken control of Apache by default now.
     *
     * FMS13 puts the SSL web root as a subdirectory to non-SSL webroot,
     * breaking automatic SSL usage on the same site content !?
     *
     * @return
     *  String of instructions to enable AllowOverride on FMS13 on Darwin
     *  (Apple OSX)
     */
    private function _darwinFMS13SSLAllowOverrideInstructions() {

        $fms13ApacheConfDir = "/Library/FileMaker Server/HTTPServer/conf";
        if (version_compare($this->_isApache(), '2.4', '>=')) {
            $restfmApacheConf = "httpd-RESTfm.FMS13.Apache24.OSX.conf";
        } else {
            // Otherwise we will assume the older Apache 2.2
            $restfmApacheConf = "httpd-RESTfm.FMS13.Apache22.OSX.conf";
        }

        $s = "\nFileMaker Server 13 on Apple OSX instructions:\n\n";

        if ($this->_RESTfmDocumentRoot == '/Library/FileMaker Server/HTTPServer/htdocs/RESTfm') {
            # This is the default configuration, easier to get right.
            $s .= 'Create a symbolic link by typing the following in a terminal:' . "\n";
            $s .= '    sudo ln -s "/Library/FileMaker Server/HTTPServer/htdocs/RESTfm" "/Library/FileMaker Server/HTTPServer/htdocs/httpsRoot"' . "\n";
            $s .= "\n";
            $s .= '- Reload this page.' . "\n";
        } elseif (strcasecmp(dirname($this->_RESTfmDocumentRoot), '/Library/FileMaker Server/HTTPServer/htdocs') == 0) {
            # Custom location, but still under the FMS13 document root.
            $matches = array();
            preg_match('/HTTPserver\/htdocs\/(.+)/i', $this->_RESTfmDocumentRoot, $matches);
            $customPath = $matches[1];
            $s .= '- Create a symbolic link by typing the following in a terminal:' . "\n";
            $s .= '    sudo ln -s "/Library/FileMaker Server/HTTPServer/htdocs/' . $customPath . '" "/Library/FileMaker Server/HTTPServer/htdocs/httpsRoot/' . $customPath . '"' . "\n";
            $s .= "\n";
            $s .= '- Edit "'.$fms13ApacheConfDir.'/extra/'.$restfmApacheConf.'" and replace:' . "\n";
            $s .= '    /Library/FileMaker Server/HTTPServer/htdocs/httpsRoot/RESTfm' . "\n";
            $s .= '  with:' . "\n";
            $s .= '    /Library/FileMaker Server/HTTPServer/htdocs/httpsRoot/' . $customPath . "\n";
            $s .= "\n";
            $s .= '- Stop the FileMaker service by typing the following in a terminal:' . "\n";
            $s .= '    sudo launchctl stop com.filemaker.fms' . "\n";
            $s .= "\n";
            $s .= '- Wait until the FileMaker service has completely stopped, by checking for output from the following command in a terminal:    ' . "\n";
            $s .= '  (No output means that the FileMaker service has stopped.)' . "\n";
            $s .= '    ps ax | grep -i filemaker | grep -v grep' . "\n";
            $s .= "\n";
            $s .= '- Start the FileMaker service by typing the following in a terminal:' . "\n";
            $s .= '    sudo launchctl start com.filemaker.fms' . "\n";
            $s .= "\n";
            $s .= '- Reload this page.' . "\n";
        } else {
            # No chance to work out what path the SSL document root is.
            $s .= '* Custom document root outside of FMS13 detected. Please contact Goya support: http://www.restfm.com/help' . "\n";
        }

        return $s;
    }

    /**
     * @return
     *  String of instructions to enable AllowOverride on Darwin (Apple OSX)
     */
    private function _darwinAllowOverrideInstructions() {

        // FileMaker Server 13 has taken control of Apache by default now.
        // We don't do OSX version specific instructions in this case.
        if ($this->_isDarwinFileMaker13()) {
            return $this->_darwinFMS13AllowOverrideInstructions();
        }

        // Different Apache configuration dirs on different Darwin releases.
        $darwinRelease = $this->_isDarwin();
        $apacheInstallDir = '/etc/apache2/other';   // Default.
        $darwinReleaseName = 'Default';
        if (version_compare($darwinRelease, '10', '>=') && version_compare($darwinRelease, '11', '<')) {
            // 10.x - Snow Leopard
            $darwinReleaseName = '10.6 Snow Leopard';
            $apacheInstallDir = '/etc/apache2/sites';
        } elseif (version_compare($darwinRelease, '11', '>=') && version_compare($darwinRelease, '12', '<')) {
            // 11.x - Lion
            $darwinReleaseName = '10.7 Lion';
            $apacheInstallDir = '/etc/apache2/other';

            // 'Server' versions of this release require a different directory.
            if ($this->_isDarwin107Server()) {
                $darwinReleaseName = '10.7 Lion Server';
                $apacheInstallDir = '/etc/apache2/sites';
            }
        } elseif (version_compare($darwinRelease, '12', '>=') && version_compare($darwinRelease, '13', '<')) {
            // 12.x - Mountain Lion

            // Other notes:
            // XML file: /System/Library/LaunchDaemons/org.apache.httpd.plist
            // 10.8 'Server' sets:
            //          -f /Library/Server/Web/Config/apache2/httpd_server_app.conf
            //          -D WEBSERVICE_ON
            // 10.8 'Client' does not set -f and so defaults to /etc/apache2/httpd.conf

            $darwinReleaseName = '10.8 Mountain Lion';
            $apacheInstallDir = '/etc/apache2/other';

            // 'Server' versions of this release require a different directory.
            if ($this->_isDarwinGE108Server()) {
                $darwinReleaseName = '10.8 Mountain Lion Server';
                $apacheInstallDir = '/Library/Server/Web/Config/apache2/sites';
            }
        } elseif (version_compare($darwinRelease, '13', '>=') && version_compare($darwinRelease, '14', '<')) {
            // 13.x - Mavericks

            // Other notes:
            // 'Server' and 'Client' distinction appears the same as 12.x

            $darwinReleaseName = '10.9 Mavericks';
            $apacheInstallDir = '/etc/apache2/other';

            // 'Server' versions of this release require a different directory.
            if ($this->_isDarwinGE108Server()) {
                $darwinReleaseName = '10.9 Mavericks Server';
                $apacheInstallDir = '/Library/Server/Web/Config/apache2/sites';
            }
        } elseif (version_compare($darwinRelease, '14', '>=') && version_compare($darwinRelease, '15', '<')) {
            // 14.x - Yosemite

            // Other notes:
            // 'Server' and 'Client' distinction appears the same as 12.x

            $darwinReleaseName = '10.10 Yosemite';
            $apacheInstallDir = '/etc/apache2/other';

            // 'Server' versions of this release require a different directory.
            if ($this->_isDarwinGE108Server()) {
                $darwinReleaseName = '10.10 Yosemite Server';
                $apacheInstallDir = '/Library/Server/Web/Config/apache2/sites';
            }
        } else {
            return ("\nUnknown Apple OSX release (Darwin ". $darwinRelease . '), please contact Goya for support: http://www.restfm.com/help');
        }

        $s = "\nApple OSX (Darwin " . $darwinRelease . " - " . $darwinReleaseName . ") instructions:\n\n";

        if (! is_dir($apacheInstallDir)) {
            $s .= 'Cannot find '. $apacheInstallDir . ', please contact Goya for support: http://www.restfm.com/help';
            return ($s);
        }

        if (version_compare($this->_isApache(), '2.4', '>=')) {
            $restfmApacheConf = "httpd-RESTfm.Apache24.OSX.conf";
        } else {
            // Otherwise we will assume the older Apache 2.2
            $restfmApacheConf = "httpd-RESTfm.Apache22.OSX.conf";
        }

        $s .= '- Install the httpd config file from the RESTfm package by typing the following in a terminal:' . "\n";
        $s .= '    sudo cp "'.$this->_RESTfmDocumentRoot.'/contrib/'.$restfmApacheConf.'" "'.$apacheInstallDir.'"' . "\n";

        // Editing the config should probably precede copying it to the apache
        // area, so it can be done with low user privilege. For now it is
        // left here as this sort of issue should be handled by an experienced
        // user.
        if ($this->_RESTfmDocumentRoot != '/Library/WebServer/Documents/RESTfm') {
            $s .= "\n";
            $s .= '- Edit '.$apacheInstallDir.'/'.$restfmApacheConf.' and replace:' . "\n";
            $s .= '    /Library/WebServer/Documents/RESTfm' . "\n";
            $s .= '  with:' . "\n";
            $s .= '    ' . $this->_RESTfmDocumentRoot . "\n";
        }

        $s .= "\n";
        $s .= '- Restart the Apache web server by typing the following in a terminal:' . "\n";
        $s .= '    sudo /usr/sbin/apachectl restart' . "\n";

        $s .= "\n";
        $s .= '- Reload this page.' . "\n";

        $s .= "\n";
        return($s);
    }
}

/**
 * Iterable class of ReportItems.
 */
class Report implements Iterator {

    private $_items;
    private $_format = 'html';

    public function __construct() {
        $this->_items = array();
    }

    function rewind() {
        return reset($this->_items);
    }

    function current() {
        return current($this->_items);
    }

    function key() {
        return key($this->_items);
    }

    function next() {
        return next($this->_items);
    }

    function valid() {
        return key($this->_items) !== NULL;
    }

    /**
     * Set the output format of a Report instance.
     *
     * @param string $format One of: html, text
     */
    function setFormat($format) {
        $this->_format = $format;
    }

    function __set($key, ReportItem $item) {
            $this->_items[$key] = $item;
    }

    function __get($key) {
        return $this->_items[$key];
    }

    function __toString() {
        $s = "";        // String to populate and return.

        if ($this->_format == 'html') {
            $s .= "\n";

            $s .= '<div id="RESTfm_Diagnostic_Report">' . "\n";
            $s .= '<table>' . "\n";
            foreach ($this->_items as $key => $item) {
                if ($item->status == ReportItem::NA) {
                    continue;
                }
                $s .= '<tr class="' . $item->status . '">' . "\n";
                $s .= '<td>' . $item->name . '</td>' . "\n";
                $s .= '<td><pre>' . $item->details . '</pre></td>' . "\n";
                $s .= "</tr>\n";
            }
            $s .= '</table>' . "\n";
            $s .= <<<EOLEGEND
<br>
Report legend:
<table>
<tr class="OK"><td>Green</td><td>OK.</td></tr>
<tr class="WARN"><td>Yellow</td><td>Warning, but not enough to stop RESTfm from working.</td></tr>
<tr class="ERROR"><td>Red</td><td>Critical error preventing RESTfm from working.</td></tr>
</table>
EOLEGEND;
// Removed INFO classification, we don't use it. Use OK/Green instead.
// <tr class="INFO"><td>Blue</td><td>Information.</td></tr>

            $s .= '</div>' . "\n";

            $s .= "\n";
        } else {    // Defaults to text format.
            foreach ($this->_items as $key => $item) {
                if ($item->status == ReportItem::NA) {
                    continue;
                }
                $s .= $item->status . ': ' . $item->name . "\n";
                // Indent all details lines with two spaces.
                foreach(explode("\n", rtrim($item->details)) as $details_line) {
                    $s .= '  ' . $details_line . "\n";
                }
            }
        }

        return($s);
    }
}

/**
 * Discreet report item.
 */
class ReportItem {

    // Error levels.
    const NA = 'NA';                // Item is Not Applicable and was skipped.
    const OK = 'OK';
    const INFO = 'INFO';
    const WARN = 'WARN';
    const ERROR = 'ERROR';

    /**
     * @var string Descriptive name of item reported.
     */
    public $name;

    /**
     * @var string Status of item reported.
     *  Default: OK
     */
    public $status = self::OK;

    /**
     * @var string Status details.
     *  May be empty if status OK.
     */
    public $details;
}
