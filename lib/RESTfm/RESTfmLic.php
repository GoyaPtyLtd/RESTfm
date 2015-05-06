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
 * Provides basic licence file loading and parsing for RESTfm.
 *
 * This class is easy to bypass, that is intentional. A stronger licence
 * enforcing scheme is encoded elsewhere. This class is only used
 * for providing licence information.
 */
class RESTfmLic {

    // -- Private properties -- //

    /**
     * @var array
     *  Parsed licence fields.
     */
    private $_licence = array();

    /**
     * @var string
     *  Raw licence file.
     */
    private $_rawLicence = "";

    /**
     * @var array
     *  Local integrity test result.
     */
    private $_licenceIntegrity = FALSE;

    /**
     * Loads and parses licence file.
     */
    public function __construct () {
        $integritySalt = 'alpha';   // This is constant at the moment.
        $concatData = '';
        $this->_rawLicence = @ file_get_contents('Licence.php');
        $inLic = FALSE;
        foreach (preg_split('/\r|\n|\r\n/', $this->_rawLicence) as $line) {
            if (strpos($line, '--- Goya Lic') === 0) {
                if ($inLic) { $inLic = FALSE; }
                elseif (! $inLic) { $inLic = TRUE; }
                continue;
            }
            if (! $inLic) { continue; }
            $a = preg_split('/\s*:\s*/', $line);
            if (isset($a[0]) && !empty($a[0]) && isset($a[1]) && !empty($a[1])) {
                $this->_licence[$a[0]] = $a[1];
                if ($a[0] !== 'Request' && $a[0] !== 'Key') {
                    $concatData .= $a[0] . $a[1];
                }
            }
        }
        #echo $concatData . $integritySalt . "\n";
        #var_dump($this->_licence);
        if (isset($this->_licence['Request']) && md5($concatData . $integritySalt) == $this->_licence['Request']) {
            $this->_licenceIntegrity = TRUE;
        }
    }

    /**
     * Returns true if a licence exists.
     */
    public function exists () {
        if (!empty($this->_licence)) { return TRUE; } else { return FALSE; }
    }

    /**
     * Returns true if local integrity check is good.
     */
    public function integrity () {
        return $this->_licenceIntegrity;
    }

    /**
     * Returns true if goya server validates licence as good.
     */
    public function validate () {
        return TRUE;
        //return "Some server error.";
    }

    /**
     * Magic method.
     */
    public function __toString () {
        if (empty($this->_licence)) {
            return 'No licence found.';
        }
        // Find maximum key length for padding.
        $maxKeyLen = 0;
        foreach ($this->_licence as $key => $val) {
            $keyLen = strlen($key);
            if ($keyLen > $maxKeyLen) { $maxKeyLen = $keyLen; }
        }
        // Produce output.
        $s = '';
        foreach ($this->_licence as $key => $val) {
            $keyLen = strlen($key);
            $padLen = $maxKeyLen - $keyLen;
            $s .= $key . str_repeat(' ', $padLen) . ' : ' . $val . "\n";
        }
        return $s;
    }

};
