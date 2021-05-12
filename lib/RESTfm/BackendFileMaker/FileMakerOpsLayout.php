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
 * FileMakerOpsLayout
 *
 * FileMaker specific implementation of OpsLayoutAbstract.
 */
class FileMakerOpsLayout extends \RESTfm\OpsLayoutAbstract {

    // --- OpsRecordLayout implementation ---

    /**
     * Construct a new Record-level Operation object.
     *
     * @param \RESTfm\BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     * @param string $layout
     */
    public function __construct (\RESTfm\BackendAbstract $backend, $database, $layout) {
        $this->_backend = $backend;
        $this->_database = $database;
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
        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        $selectList = array();

        // New FileMaker find command.
        if (count($this->_findCriteria) > 0) {
            // This search query will contain criterion.
            $findCommand = $FM->newFindCommand($this->_layout);
            foreach ($this->_findCriteria as $fieldName => $testValue) {
                // Strip index suffix '[n]' from $fieldName for repetitions.
                $matches = array();
                if (preg_match('/^(.+)\[(\d+)\]$/', $fieldName, $matches)) {
                    $fieldName = $matches[1];   // Real fieldName minus index.
                }
                $findCommand->addFindCriterion($fieldName, $testValue);
            }
        } elseif ($this->_SQLquery !== NULL) {
            // This search is using SQL-like syntax.
            $parser = new FileMakerSQLParser($this->_SQLquery, $FM, $this->_layout);
            //$parser->setDebug(TRUE);
            $parser->parse();

            $findCommand = $parser->getFMFind();
            $selectList = $parser->getSelectList();
        } else {
            // No criteria, so 'find all'.
            $findCommand = $FM->newFindAllCommand($this->_layout);
        }

        // Script calling.
        if ($this->_postOpScript !== NULL) {
            $findCommand->setScript($this->_postOpScript, $this->_postOpScriptParameter);
        }
        if ($this->_preOpScript != NULL) {
            $findCommand->setPreCommandScript($this->_preOpScript, $this->_preOpScriptParameter);
        }

        $findSkip = $this->_readOffset;
        $findMax = $this->_readCount;

        // Use SQL OFFSET and LIMIT where available.
        if ($this->_SQLquery !== NULL) {
            // Always override by SQL LIMIT clause.
            if ($parser->getLimit() !== NULL ) {
                $findMax = $parser->getLimit();
            }

            // Override by SQL OFFSET clause only if currently zero.
            // This allows RFMskip paging to work as expected with SQL queries.
            if ($parser->getOffset() !== NULL && $findSkip == 0) {
                $findSkip = $parser->getOffset();
            }
        }

        // Confine results range from calculated skip and max values.
        $findCommand->setRange($findSkip, $findMax);

        // Query FileMaker
        $result = $findCommand->execute();

        if (\FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }

        $restfmMessage = new \RESTfm\Message\Message();

        $this->_parseMetaField($restfmMessage, $result);
        $metaFields = $restfmMessage->getMetaFields();

        // Process records and push data.
        $fieldNames = $result->getFields();
        // An empty $selectList, or '*' anywhere in $selectList means that
        // all $fieldNames will be returned.
        if (! empty($selectList) && ! in_array('*', $selectList)) {
            // Restrict $fieldNames to those that are common with $selectList,
            // preserving $selectList order.
            $fieldNames = array_intersect($selectList, $fieldNames);
        }
        foreach ($result->getRecords() as $record) {
            // @TODO This code is duplicated in FileMakerOpsRecord, could be
            //       moved into a static FileMakerParser::record($restfmMessage, $record).
            $restfmMessageRecord = new \RESTfm\Message\Record($record->getRecordId());
            foreach ($fieldNames as $fieldName) {
                $metaFieldRow = NULL; // @var \RESTfm\Message\Row
                $metaFieldRow = $metaFields[$fieldName];

                // Field repetitions are expanded into multiple fields with
                // an index operator suffix; fieldName[0], fieldName[1] ...
                $fieldRepeat = $metaFieldRow['maxRepeat'];
                for ($repetition = 0; $repetition < $fieldRepeat; $repetition++) {
                    $fieldNameRepeat = $fieldName;

                    // Apply index suffix  only when more than one $fieldRepeat.
                    if ($fieldRepeat > 1) {
                        $fieldNameRepeat .= '[' . $repetition . ']';
                    }

                    // Get un-mangled field data, usually this is all we need.
                    $fieldData = $record->getFieldUnencoded($fieldName, $repetition);

                    // Handle container types differently.
                    if ($metaFieldRow['resultType'] == 'container') {
                        switch ($this->_containerEncoding) {
                            case self::CONTAINER_BASE64:
                                $filename = '';
                                $matches = array();
                                if (preg_match('/^\/fmi\/xml\/cnt\/([^\?]*)\?/', $fieldData, $matches)) {
                                    $filename = $matches[1] . ';';
                                }
                                $containerData = $FM->getContainerData($record->getField($fieldName, $repetition));
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
                                if (method_exists($FM, 'getContainerDataURL')) {
                                    // Note: FileMaker::getContainerDataURL() only exists in the FMSv12 PHP API
                                    $fieldData = $FM->getContainerDataURL($record->getField($fieldName, $repetition));
                                }
                        }
                    }

                    // Store this field's data for this row.
                    $restfmMessageRecord[$fieldNameRepeat] = $fieldData;
                }
            }
            $restfmMessage->addRecord($restfmMessageRecord);
        }

        // Info.
        $restfmMessage->setInfo('tableRecordCount', $result->getTableRecordCount());
        $restfmMessage->setInfo('foundSetCount', $result->getFoundSetCount());
        $restfmMessage->setInfo('fetchCount', $result->getFetchCount());

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
        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        $layoutResult = $FM->getLayout($this->_layout);
        if (\FileMaker::isError($layoutResult)) {
            throw new FileMakerResponseException($layoutResult);
        }

        $restfmMessage = new \RESTfm\Message\Message();

        $this->_parseMetaField($restfmMessage, $layoutResult);

        return $restfmMessage;
    }

    // --- Protected ---

    /**
     * @var string
     *  Database name.
     */
    protected $_database;

    /**
     * @var string
     *  Layout name.
     */
    protected $_layout;

    /**
     * Parse field meta data out of provided FileMaker result object into
     * provided \RESTfm\Message\Message object.
     *
     * @TODO This code is duplicated in FileMakerOpsRecord, could be
     *       moved into a static FileMakerParser::metaField($restfmMessage, $result).
     *
     * @param \RESTfm\Message\Message $restfmMessage
     * @param FileMaker_Result|FileMaker_Layout $result
     */
    protected function _parseMetaField(\RESTfm\Message\Message $restfmMessage, $result) {

        if (is_a($result, 'FileMaker_Result')) {
            $layoutResult = $result->getLayout();
        } elseif (is_a($result, 'FileMaker_Layout')) {
            $layoutResult = $result;
        } else {
            return;
        }

        // Dig out field meta data from field objects in layout object returned
        // by result object!
        $fieldNames = $layoutResult->listFields();
        foreach ($fieldNames as $fieldName) {
            $fieldResult = $layoutResult->getField($fieldName);

            $restfmMessageRow = new \RESTfm\Message\Row();

            $restfmMessageRow['name'] = $fieldName;
            $restfmMessageRow['autoEntered'] = $fieldResult->isAutoEntered() ? 1 : 0;
            $restfmMessageRow['global'] = $fieldResult->isGlobal() ? 1 : 0;
            $restfmMessageRow['maxRepeat'] = $fieldResult->getRepetitionCount();
            $restfmMessageRow['resultType'] = $fieldResult->getResult();
            //$restfmMessageRow['type'] = $fieldResult->getType();

            $restfmMessage->setMetaField($fieldName, $restfmMessageRow);
        }
    }

};
