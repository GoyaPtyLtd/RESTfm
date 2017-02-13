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

/**
 * PdoOpsDatabase
 *
 * PHP PDO specific implementation of OpsDatabaseAbstract.
 */
class PdoOpsDatabase extends OpsDatabaseAbstract {

    // --- OpsRecordDatabase implementation ---

    public function __construct (BackendAbstract $backend, $database = NULL) {
        $this->_backend = $backend;
        $this->_database = $database;
    }

    /**
     * Read databases available - Not applicable to PDO backends, as the database
     * is hard coded in the DSN.
     *
     * @return RESTfmMessage
     *   Empty, no sections.
     */
    public function readDatabases () {
        return new RESTfmMessage;
    }

    /**
     * Read tables available in database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmMessage
     *  - 'data', 'meta' sections.
     */
    public function readLayouts () {
        $pdo = $this->_backend->getPDO();
        try {
            // MySQL:
            $result = $pdo->query('SHOW TABLES', PDO::FETCH_NUM);
        } catch (PDOException $e) {
            throw new PdoResponseException($e);
        }

        $tables = array();
        foreach ($result as $row) {
            array_push($tables, $row[0]);
        }

        natsort($tables);

        $restfmMessage = new RESTfmMessage();
        foreach($tables as $table) {
            if (empty($table)) continue;
            $restfmMessage->addRecord(new RESTfmMessageRecord(
                NULL,
                NULL,
                array('layout' => $table)
            ));
        }

        return $restfmMessage;
    }

    /**
     * Read scripts available in $database via backend. - Not applicable.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmMessage
     *  - 'data', 'meta' sections.
     */
    public function readScripts () {
        return new RESTfmMessage();
    }

    // --- Protected ---

};
