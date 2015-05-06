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

require_once 'FileMakerResponseException.php';

/**
 * FileMakerOpsDatabase
 *
 * FileMaker specific implementation of OpsDatabaseAbstract.
 */
class FileMakerOpsDatabase extends OpsDatabaseAbstract {

    // --- OpsRecordDatabase implementation ---

    public function __construct (BackendAbstract $backend, $database = NULL) {
        $this->_backend = $backend;
        if ($database != NULL) {
            $this->_backend->getFileMaker()->setProperty('database', $database);
        }
        $this->_database = $database;
    }

    /**
     * Read databases available.
     *
     * @throws FileMakerResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta' sections.
     */
    public function readDatabases () {
        $FM = $this->_backend->getFileMaker();
        $result = $FM->listDatabases();
        if (FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }
        natsort($result);

        $restfmData = new RESTfmDataSimple();
        foreach($result as $database) {
            $restfmData->pushDataRow( array('database' => $database), NULL, NULL );
        }

        return $restfmData;
    }

    /**
     * Read layouts available in $database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta' sections.
     */
    public function readLayouts () {
        $FM = $this->_backend->getFileMaker();
        $result = $FM->listLayouts();
        if (FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }
        natsort($result);

        $restfmData = new RESTfmDataSimple();
        foreach($result as $layout) {
            if (empty($layout)) continue;
            $restfmData->pushDataRow( array('layout' => $layout), NULL, NULL );
        }

        return $restfmData;
    }

    /**
     * Read scripts available in $database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta' sections.
     */
    public function readScripts () {
        $FM = $this->_backend->getFileMaker();
        $result = $FM->listScripts();
        if (FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }
        natsort($result);

        $restfmData = new RESTfmDataSimple();
        foreach($result as $script) {
            $restfmData->pushDataRow( array('script' => $script), NULL, NULL );
        }

        return $restfmData;
    }

    // --- Protected ---

};
