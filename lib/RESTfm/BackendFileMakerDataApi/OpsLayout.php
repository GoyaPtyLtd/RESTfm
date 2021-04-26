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
     * @var \RESTfm\BackendFileMakerDataApi\Backend
     *  Handle to backend object.
     */
    protected $_backend = NULL;

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

        $selectList = array();

        $findSkip = $this->_readOffset;
        $findMax = $this->_readCount;

        if ($findSkip == -1) {
            // If we are to skip to the end ...
            // ... not possible with this backend.
        }

        // Query.
        if ($this->_SQLquery !== NULL) {
            // This search is using SQL-like syntax.
            $parser = new FileMakerDataApiSQLParser($this->_SQLquery);
            //$parser->setDebug(TRUE);
            $parser->parse();

            // Iff SQL OFFSET is set, use it for $findSkip.
            // Otherwise RFMskip paging will work with SQL queries.
            if ($parser->getOffset() !== NULL) {
                $findSkip = $parser->getOffset();
            }

            $result = $fmDataApi->findRecords($this->_layout,
                                              $parser->getQuery(),
                                              array(),
                                              $parser->getSort(),
                                              $findSkip + 1,
                                              $parser->getLimit());
            $selectList = $parser->getSelectList();
        } else {
            $result = $fmDataApi->getRecords($this->_layout, $findMax, $findSkip + 1);
        }


        if ($result->isError()) {
            throw new FileMakerDataApiResponseException($result);
        }

        $restfmMessage = new \RESTfm\Message\Message();

        /*
        $restfmMessage->addRecord(new \RESTfm\Message\Record(
            $record['recordId'],
            NULL,
            $record['fieldData']
        ));
        */

        $fieldNames = $result->getFields();
        // An empty $selectList, or '*' anywhere in $selectList means that
        // all $fieldNames will be returned.
        if (! empty($selectList) && ! in_array('*', $selectList)) {
            // Restrict $fieldNames to those that are common with $selectList,
            // preserving $selectList order.
            $fieldNames = array_intersect($selectList, $fieldNames);
        }

        foreach ($result->getRecords() as $record) {
            $restfmMessageRecord = new \RESTfm\Message\Record($record['recordId']);
            foreach ($fieldNames as $fieldName) {
                $fieldData = $record['fieldData'][$fieldName];
                // Store this field's data for this row.
                $restfmMessageRecord[$fieldName] = $fieldData;
            }
            $restfmMessage->addRecord($restfmMessageRecord);
        }

        // Info.
        $restfmMessage->setInfo('tableRecordCount', $result->getTotalRecordCount());
        $restfmMessage->setInfo('foundSetCount', $result->getFoundCount());
        $restfmMessage->setInfo('fetchCount', $result->getReturnedCount());

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
