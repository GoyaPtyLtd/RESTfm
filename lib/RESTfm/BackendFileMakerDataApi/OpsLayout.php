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
     * @var string
     *  Layout name.
     */
    protected $_layout;

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

        $params = array();

        // Script calling.
        $this->_scriptPropertiesToParams($params);

        $selectList = array();

        $findSkip = $this->_readOffset;
        $findMax = $this->_readCount;

        if ($findSkip == -1) {
            // If we are to skip to the end ...
            // ... not possible with this backend.
        }

        // Query.
        if (count($this->_findCriteria) > 0) {
            // This search query will contain criterion.
            $query = array();
            $query[0] = $this->_findCriteria;

            $result = $fmDataApi->findRecords($this->_layout,
                                              $query,
                                              $params,
                                              array(),
                                              $findSkip + 1,
                                              $findMax);
        } elseif ($this->_SQLquery !== NULL) {
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
                                              $params,
                                              $parser->getSort(),
                                              $findSkip + 1,
                                              $parser->getLimit());
            $selectList = $parser->getSelectList();
        } else {
            $result = $fmDataApi->getRecords($this->_layout,
                                             $findMax,
                                             $findSkip + 1,
                                             NULL,
                                             $params);
        }

        if ($result->isError() || $result->getFetchCount() < 1) {
            throw new FileMakerDataApiResponseException($result);
        }

        $restfmMessage = new \RESTfm\Message\Message();

        $fieldNames = $result->getFields();
        // An empty $selectList, or '*' anywhere in $selectList means that
        // all $fieldNames will be returned.
        if (! empty($selectList) && ! in_array('*', $selectList)) {
            // Restrict $fieldNames to those that are common with $selectList,
            // preserving $selectList order.
            $fieldNames = array_intersect($selectList, $fieldNames);
        }

        if ($this->_containerEncoding !== self::CONTAINER_DEFAULT) {
            // Since some kind of container encoding is requested, we need to
            // request field metadata so we can work out which field(s) it is.
            $metaDataResult = $fmDataApi->layoutMetadata($this->_layout);
            $fieldMetaData = $metaDataResult->getFieldMetaData();
            $containerFields = array();
            foreach ($fieldMetaData as $data) {
                if (isset($data['name']) &&
                        isset($data['result']) &&
                        $data['result'] == 'container') {
                    $containerFields[$data['name']] = 1;
                }
            }
        }

        foreach ($result->getRecords() as $record) {
            $restfmMessageRecord = new \RESTfm\Message\Record($record['recordId']);
            foreach ($fieldNames as $fieldName) {
                $fieldData = $record['fieldData'][$fieldName];
                if ($this->_containerEncoding !== self::CONTAINER_DEFAULT &&
                        array_key_exists($fieldName, $containerFields)) {
                    switch ($this->_containerEncoding) {
                        case self::CONTAINER_BASE64:
                            $filename = '';
                            $matches = array();
                            if (preg_match('/\/([^\/\?]*)\?/', $fieldData, $matches)) {
                                $filename = $matches[1] . ';';
                            }
                            $containerData = $fmDataApi->getContainerData($fieldData);
                            if (gettype($containerData) !== 'string') {
                                $containerData = "";
                            }
                            $fieldData = $filename . base64_encode($containerData);
                            break;
                        case self::CONTAINER_RAW:
                            // TODO
                            break;
                        case self::CONTAINER_DEFAULT:
                        default:
                            // Do nothing
                    }
                }
                // Store this field's data for this row.
                $restfmMessageRecord[$fieldName] = $fieldData;
            }
            $restfmMessage->addRecord($restfmMessageRecord);
        }

        // Info.
        $restfmMessage->setInfo('tableRecordCount', $result->getTotalRecordCount());
        $restfmMessage->setInfo('foundSetCount', $result->getFoundCount());
        $restfmMessage->setInfo('fetchCount', $result->getReturnedCount());

        // Script results.
        $this->_scriptResultsToInfo($restfmMessage, $result);

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
        $fmDataApi = $this->_backend->getFileMakerDataApi();

        $result = $fmDataApi->layoutMetadata($this->_layout);
        $fieldMetaData = $result->getFieldMetaData();

        $restfmMessage = new \RESTfm\Message\Message();

        foreach ($fieldMetaData as $data) {
            if (isset($data['name'])) {
                $row = new \RESTfm\Message\Row($data);
                $restfmMessage->setMetaField($data['name'], $row);
            }
        }

        return $restfmMessage;
    }

    /**
     * Build an array of FileMakerDataApi "params" from our script related
     * properties.
     *
     * @param array &$params
     *  Array to populate with script related parameters.
     */
    protected function _scriptPropertiesToParams (array &$params) {
        if ($this->_postOpScript !== NULL) {
            $params['script'] = $this->_postOpScript;
            if ($this->_postOpScriptParameter !== NULL) {
                $params['script.param'] = $this->_postOpScriptParameter;
            }
        }
        if ($this->_preOpScript !== NULL) {
            $params['script.prerequest'] = $this->_preOpScript;
            if ($this->_preOpScriptParameter !== NULL) {
                $params['script.prerequest.param'] = $this->_preOpScriptParameter;
            }
        }
    }

    /**
     * Insert any script responses into 'info' section of restfmMessage.
     *
     * @param \RESTfm\Message\Message $restfmMessage
     *  Message object to set 'info'.
     * @param \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult $result
     *  Result from querying FileMaker Data API.
     */
    protected function _scriptResultsToInfo (\RESTfm\Message\Message $restfmMessage, \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult $result) {
        $scriptResults = $result->getScriptResults();
        foreach ($scriptResults as $res => $val) {
            $restfmMessage->setInfo($res, $val);
        }
    }

};
