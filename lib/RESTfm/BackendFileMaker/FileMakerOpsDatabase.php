<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2017 Goya Pty Ltd.
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

namespace RESTfm\BackendFileMaker;

/**
 * FileMakerOpsDatabase
 *
 * FileMaker specific implementation of OpsDatabaseAbstract.
 */
class FileMakerOpsDatabase extends \RESTfm\OpsDatabaseAbstract {

    // --- OpsRecordDatabase implementation ---

    public function __construct (\RESTfm\BackendAbstract $backend, $database = NULL) {
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
     * @return \RESTfm\Message\Message
     */
    public function readDatabases () {
        $FM = $this->_backend->getFileMaker();
        $result = $FM->listDatabases();
        if (\FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }
        natsort($result);

        $restfmMessage = new \RESTfm\Message\Message();
        foreach($result as $database) {
            $restfmMessage->addRecord(new \RESTfm\Message\Record(
                NULL, NULL, array('database' => $database)
            ));
        }

        return $restfmMessage;
    }

    /**
     * Read layouts available in $database via backend.
     *
     * @throws \RESTfm\ResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readLayouts () {
        $FM = $this->_backend->getFileMaker();
        $result = $FM->listLayouts();
        if (\FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }
        natsort($result);

        $restfmMessage = new \RESTfm\Message\Message();
        foreach($result as $layout) {
            if (empty($layout)) continue;
            $restfmMessage->addRecord(new \RESTfm\Message\Record(
                NULL, NULL, array('layout' => $layout)
            ));
        }

        return $restfmMessage;
    }

    /**
     * Read scripts available in $database via backend.
     *
     * @throws \RESTfm\ResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readScripts () {
        $FM = $this->_backend->getFileMaker();
        $result = $FM->listScripts();
        if (\FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }
        natsort($result);

        $restfmMessage = new \RESTfm\Message\Message();
        foreach($result as $script) {
            $restfmMessage->addRecord(new \RESTfm\Message\Record(
                NULL, NULL, array('script' => $script)
            ));
        }

        return $restfmMessage;
    }

    // --- Protected ---

};
