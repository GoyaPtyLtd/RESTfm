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

namespace RESTfm\BackendFileMakerDataApi;

/**
 * FileMaker Data API implementation of OpsDatabaseAbstract.
 */
class OpsDatabase extends \RESTfm\OpsDatabaseAbstract {

    /**
     * Construct a new Database-level Operation object.
     *
     * @param \RESTfm\BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     */
    public function __construct (\RESTfm\BackendAbstract $backend, $database = NULL) {
        $this->_backend = $backend;
    }

    /**
     * Read databases available.
     *
     * @throws FileMakerDataApiResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readDatabases () {
        // @var FileMakerDataApi
        $fmDataApi = $this->_backend->getFileMakerDataApi();

         // @var FileMakerDataApiResult
        $result = $fmDataApi->databaseNames();

        if ($result->isError()) {
            throw FileMakerDataApiResponseException($result);
        }

        $databases = $result->getDatabases();
        $databaseNames = array();
        foreach ($databases as $database) {
            array_push($databaseNames, $database['name']);
        }
        natsort($databaseNames);

        $restfmMessage = new \RESTfm\Message\Message;
        foreach ($databaseNames as $database) {
            $restfmMessage->addRecord(new \RESTfm\Message\Record(
                NULL, NULL, array('database' => $database)
            ));
        }

        return $restfmMessage;
    }

    /**
     * Read layouts available in database via backend.
     *
     * @throws FileMakerDataApiResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readLayouts () {
        // @var FileMakerDataApi
        $fmDataApi = $this->_backend->getFileMakerDataApi();

         // @var FileMakerDataApiResult
        $result = $fmDataApi->layoutNames();

        if ($result->isError()) {
            throw FileMakerDataApiResponseException($result);
        }

        $layouts = $result->getLayouts();
        $layoutNames = array();
        foreach ($layouts as $layout) {
            array_push($layoutNames, $layout['name']);
        }
        natsort($layoutNames);

        $restfmMessage = new \RESTfm\Message\Message();
        foreach ($layoutNames as $layout) {
            $restfmMessage->addRecord(new \RESTfm\Message\Record(
                NULL, NULL, array('layout' => $layout)
            ));
        }

        return $restfmMessage;
    }

    /**
     * Read scripts available in database via backend.
     *
     * @throws \RESTFm\ResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readScripts () {
        // @var FileMakerDataApi
        $fmDataApi = $this->_backend->getFileMakerDataApi();

         // @var FileMakerDataApiResult
        $result = $fmDataApi->scriptNames();

        if ($result->isError()) {
            throw FileMakerDataApiResponseException($result);
        }

        $scripts = $result->getScripts();
        $scriptNames = array();
        foreach ($scripts as $script) {
            array_push($scriptNames, $script['name']);
        }
        natsort($scriptNames);

        $restfmMessage = new \RESTfm\Message\Message();
        foreach ($scriptNames as $script) {
            $restfmMessage->addRecord(new \RESTfm\Message\Record(
                NULL, NULL, array('script' => $script)
            ));
        }

        return $restfmMessage;
    }

};
