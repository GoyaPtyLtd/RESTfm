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
     * @var \RESTfm\BackendFileMakerDataApi\Backend
     *  Handle to backend object.
     */
    protected $_backend = NULL;

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
            throw new FileMakerDataApiResponseException($result);
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
            throw new FileMakerDataApiResponseException($result);
        }

        $layouts = $result->getLayouts();
        $layoutNames = $this->_flattenFolders($layouts);
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
            throw new FileMakerDataApiResponseException($result);
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

    /**
     * Traverse and recurse the given layout hierarchy and flatten any
     * folders found. We understand that layout names should be unique, and
     * absent folder names will not alter how they are called by RESTfm.
     *
     * @param array $layouts
     *  Array of layouts as returned by the Data API, in the form of:
     * array (
     *  0 =>
     *  array (
     *    'name' => 'full postcodes',
     *  ),
     *  1 =>
     *  array (
     *    'name' => 'Folder',
     *    'isFolder' => true,
     *    'folderLayoutNames' =>
     *    array (
     *      0 =>
     *      array (
     *        'name' => 'postcodes',
     *      ),
     *      1 =>
     *      array (
     *        'name' => 'postcodes.2',
     *      ),
     *      2 =>
     *      array (
     *        'name' => 'Folder.2',
     *        'isFolder' => true,
     *        'folderLayoutNames' =>
     *        array (
     *          0 =>
     *          array (
     *            'name' => 'postcodes.3',
     *          ),
     *        ),
     *      ),
     *    ),
     *  ),
     *
     * @return array
     *  of flattened layout names.
     */
    private function _flattenFolders (array $layouts) {
        $layoutNames = array();
        foreach ($layouts as $layout) {
            if (isset($layout['isFolder']) && $layout['isFolder'] === true) {
                $flattened = array();
                if (isset($layout['folderLayoutNames'])) {
                    $flattened = $this->_flattenFolders($layout['folderLayoutNames']);
                }
                $layoutNames = array_merge($layoutNames, $flattened);
            } else {
                array_push($layoutNames, $layout['name']);
            }
        }
        return $layoutNames;
    }
};
