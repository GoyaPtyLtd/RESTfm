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
require_once 'FileMakerSQLParser.php';

/**
 * FileMakerOpsLayout
 *
 * FileMaker specific implementation of OpsLayoutAbstract.
 */
class FileMakerOpsLayout extends OpsLayoutAbstract {

    // --- OpsRecordLayout implementation ---

    /**
     * Construct a new Record-level Operation object.
     *
     * @param BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     * @param string $layout
     */
    public function __construct (BackendAbstract $backend, $database, $layout) {
        $this->_backend = $backend;
        $this->_database = $database;
        $this->_layout = $layout;
    }

    /**
     * Read records in layout in database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta', 'metaField' sections.
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

        if (FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }

        $restfmData = new RESTfmDataSimple();

        $this->_parseMetaField($restfmData, $result);

        // Process records and push data.
        $fieldNames = $result->getFields();
        if (! empty($selectList)) {     // Empty select list is considered "*".
            // Only keep fieldNames that are common to both, preserving
            // $selectList order.
            $fieldNames = array_intersect($selectList, $fieldNames);
        }
        foreach ($result->getRecords() as $record) {
            // @todo This code is duplicated in FileMakerOpsRecord, should be
            //       moved into a static FileMakerParser::record($restfmData, $record).
            $recordRow = array();
            $recordID = $record->getRecordId();
            foreach ($fieldNames as $fieldName) {
                // Field repetitions are expanded into multiple fields with
                // an index operator suffix; fieldName[0], fieldName[1] ...
                $fieldRepeat = $restfmData->getFieldMetaValue($fieldName, 'maxRepeat');

                for ($repetition = 0; $repetition < $fieldRepeat; $repetition++) {
                    $fieldNameRepeat = $fieldName;

                    // Apply index suffix  only when more than one $fieldRepeat.
                    if ($fieldRepeat > 1) {
                        $fieldNameRepeat .= '[' . $repetition . ']';
                    }

                    // Get un-mangled field data, usually this is all we need.
                    $fieldData = $record->getFieldUnencoded($fieldName, $repetition);

                    // Handle container types differently.
                    if ($restfmData->getFieldMetaValue($fieldName, 'resultType') == 'container') {
                        switch ($this->_containerEncoding) {
                            case self::CONTAINER_BASE64:
                                $filename = '';
                                $matches = array();
                                if (preg_match('/^\/fmi\/xml\/cnt\/([^\?]*)\?/', $fieldData, $matches)) {
                                    $filename = $matches[1] . ';';
                                }
                                $fieldData = $filename . base64_encode($FM->getContainerData($record->getField($fieldName, $repetition)));
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
                    $recordRow[$fieldNameRepeat] = $fieldData;
                }
            }
            $restfmData->pushDataRow($recordRow, $recordID, NULL);
        }

        // Info.
        $restfmData->pushInfo('tableRecordCount', $result->getTableRecordCount());
        $restfmData->pushInfo('foundSetCount', $result->getFoundSetCount());
        $restfmData->pushInfo('fetchCount', $result->getFetchCount());

        return $restfmData;
    }

    /**
     * Read field metadata in layout in database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'metaField' section.
     */
    public function readMetaField () {
        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        $layoutResult = $FM->getLayout($this->_layout);
        if (FileMaker::isError($layoutResult)) {
            throw new FileMakerResponseException($layoutResult);
        }

        $restfmData = new RESTfmDataSimple();

        $this->_parseMetaField($restfmData, $layoutResult);

        return $restfmData;
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
     * provided RESTfmData object.
     *
     * @todo This code is duplicated in FileMakerOpsRecord, should be
     *       moved into a static FileMakerParser::metaField($restfmData, $result).
     *
     * @param RESTfmDataSimple $restfmData
     * @param FileMaker_Result|FileMaker_Layout $result
     */
    protected function _parseMetaField(RESTfmDataSimple $restfmData, $result) {

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
            $fieldMeta = array();
            $fieldResult = $layoutResult->getField($fieldName);

            $fieldMeta['autoEntered'] = $fieldResult->isAutoEntered() ? 1 : 0;
            $fieldMeta['global'] = $fieldResult->isGlobal() ? 1 : 0;
            $fieldMeta['maxRepeat'] = $fieldResult->getRepetitionCount();
            $fieldMeta['resultType'] = $fieldResult->getResult();
            //$fieldMeta['type'] = $fieldResult->getType();

            $restfmData->pushFieldMeta($fieldName, $fieldMeta);
        }
    }

};
