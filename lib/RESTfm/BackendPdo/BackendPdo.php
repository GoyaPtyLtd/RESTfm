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

require_once 'PdoResponseException.php';
require_once 'PdoOpsRecord.php';
require_once 'PdoOpsDatabase.php';
require_once 'PdoOpsLayout.php';

/**
 * PHP PDO implementation of BackendAbstract.
 */
class BackendPdo extends BackendAbstract {

    /*
     * Possible PDO types.
     */
    const   PDO_MYSQL   = 1,
            PDO_SQLITE  = 2;

    // -- Private properties --

    /**
     * @var PDO
     *  Single instance of PDO object.
     */
    private $_pdoObject = NULL;

    /**
     * @var int
     *  Type of this PDO.
     */
    private $_pdoType = 0;


    // -- BackendAbstract implementation --

    /**
     * Backend Constructor.
     *
     * Instantiates and stores a FileMaker object. Sets the hostspec and
     * authentication credentials.
     *
     * @param string $dsn
     *  Data Source Name for backend database.
     * @param string $username
     * @param string $password
     */
    public function __construct ($dsn, $username, $password) {

        // Identify the type of this PDO.
        $matches = array();
        if (preg_match('/^(mysql|sqlite):/i', $dsn, $matches)) {
            $pdoType = strtolower($matches[1]);
        } else {
            error_log('RESTfm BackendPdo::__construct() error: unknown PDO type from DSN: ' . $dsn);
            throw new RESTfmResponseException('Unknown backend PDO type.', 500);
        }
        if ($pdoType == 'mysql') {
            $this->_pdoType = self::PDO_MYSQL;
        } elseif ($pdoType == 'sqlite') {
            $this->_pdoType = self::PDO_SQLITE;
        }

        // Configure default PDO options.
        $options = array(
            PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
        );
        if ($this->_pdoType == self::PDO_MYSQL) {
            $options[PDO::MYSQL_ATTR_FOUND_ROWS] = TRUE;
        }

        // Create and store PDO.
        try {
            $this->_pdoObject = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PdoResponseException($e);
        }
    }

    /**
     * Instantiate and return FileMakerOpsDatabase.
     *
     * @param string $database
     *
     * @return OpsDatabaseAbstract;
     */
    public function makeOpsDatabase ($database = NULL) {
        return new PdoOpsDatabase($this, $database);
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
        return new PdoOpsLayout($this, $database, $layout);
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
        return new PdoOpsRecord($this, $database, $layout);
    }

    // -- Other Public  --

    /**
     * Returns the FileMaker object.
     *
     * @return FileMaker
     */
    public function getPDO () {
        return $this->_pdoObject;
    }


    /**
     * Returns the PDO type.
     *
     * @return int
     */
    public function getPDOType () {
        return $this->_pdoType;
    }
};
