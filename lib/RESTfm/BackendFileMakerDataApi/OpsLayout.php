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
 * FileMaker Data API implementation of OpsLayoutAbstract.
 */
class OpsLayout extends \RESTfm\OpsLayoutAbstract {

    /**
     * Construct a new Record-level Operation object.
     *
     * @param \RESTfm\BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $mapName
     * @param string $layout
     */
    public function __construct (\RESTfm\BackendAbstract $backend, $mapName, $layout) {
        $this->_backend = $backend;
        $this->_layout = $layout;
    }

    /**
     * Read records in layout in database via backend.
     *
     * @throws \RESTfm\ResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function read () {
        $fmDataApi = $this->_backend->getFileMakerDataApi(); // @var FileMakerDataApi

        $findSkip = $this->_readOffset;
        $findMax = $this->_readCount;

        if ($findSkip == -1) {
            // If we are to skip to the end ...
            // ... not possible with this backend.
        }

        // Query.
        $result = $fmDataApi->getRecords($this->_layout, $findMax, $findSkip + 1);

        $restfmMessage = new \RESTfm\Message\Message();

        $fetchCount = 0;
        foreach ($result->getRecords() as $record) {
            $restfmMessage->addRecord(new \RESTfm\Message\Record(
                $record['recordId'],
                NULL,
                $record['fieldData']
            ));
            $fetchCount++;
        }

        // Calculate the number of records in this query till here
        // (ensures we get "next" navigation link).
        $recordCount = $findSkip + $fetchCount + 1;

        // Info.
        //$restfmMessage->setInfo('tableRecordCount', $recordCount);
        $restfmMessage->setInfo('foundSetCount', $recordCount);
        $restfmMessage->setInfo('fetchCount', $fetchCount);

        return $restfmMessage;
    }

    /**
     * Read field metadata in layout in database via backend.
     *
     * @throws \RESTfm\ResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readMetaField () {
        return new \RESTfm\Message\Message;
    }

    /**
     * @var string
     *  Layout name.
     */
    protected $_layout;
};
