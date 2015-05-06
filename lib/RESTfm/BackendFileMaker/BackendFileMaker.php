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

require_once 'FileMaker.php';
require_once 'FileMakerOpsRecord.php';
require_once 'FileMakerOpsDatabase.php';
require_once 'FileMakerOpsLayout.php';

/**
 * FileMaker implementation of BackendAbstract.
 */
class BackendFileMaker extends BackendAbstract {

    // -- Private properties --

    /**
     * @var FileMaker
     *  Single instance of FileMaker object.
     */
    private $_fmObject = NULL;


    // -- BackendAbstract implementation --

    /**
     * Backend Constructor.
     *
     * Instantiates and stores a FileMaker object. Sets the hostspec and
     * authentication credentials.
     *
     * @param string $host
     *  Hostname/Hostspec for backend database.
     * @param string $username
     * @param string $password
     */
    public function __construct ($hostspec, $username, $password) {
        $this->_fmObject = new FileMaker();

        $this->_fmObject->setProperty('hostspec', $hostspec);
        if (RESTfmConfig::getVar('settings', 'strictSSLCertsFMS') === FALSE) {
            $this->_fmObject->setProperty('curlOptions', array(
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                                CURLOPT_SSL_VERIFYHOST => FALSE,
                                ));
        }
        $this->_fmObject->setProperty('username', $username);
        $this->_fmObject->setProperty('password', $password);
    }

    /**
     * Instantiate and return FileMakerOpsDatabase.
     *
     * @param string $database
     *
     * @return OpsDatabaseAbstract;
     */
    public function makeOpsDatabase ($database = NULL) {
        return new FileMakerOpsDatabase($this, $database);
    }

    /**
     * Instantiate and return FileMakerOpsLayout.
     *
     * @param string $database
     * @param string $layout
     *
     * @return OpsLayoutAbstract;
     */
    public function makeOpsLayout ($database, $layout) {
        return new FileMakerOpsLayout($this, $database, $layout);
    }

    /**
     * Instantiate and return FileMakerOpsRecord.
     *
     * @param string $database
     * @param string $layout
     *
     * @return OpsRecordAbstract
     */
    public function makeOpsRecord ($database, $layout) {
        return new FileMakerOpsRecord($this, $database, $layout);
    }

    // -- Other Public  --

    /**
     * Returns the FileMaker object.
     *
     * @return FileMaker
     */
    public function getFileMaker () {
        return $this->_fmObject;
    }

};
