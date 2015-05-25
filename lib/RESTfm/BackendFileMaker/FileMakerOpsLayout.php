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

        // If we are to skip to the end, do a single result query to determine
        // the actual skip value.
        if ($findSkip == -1) {
            $findCommand->setRange(0, 1);
            // Query FileMaker
            $result = $findCommand->execute();
            if (FileMaker::isError($result)) {
                throw new FileMakerResponseException($result);
            }
            $findSkip = $result->getFoundSetCount() - $findMax;
            $findSkip = max(0, $findSkip);  // Ensure not less than zero.
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
                    if ($restfmData->getFieldMetaValue($fieldName, 'resultType') == 'container' && method_exists($FM, 'getContainerDataURL')) {
                        // Note: FileMaker::getContainerDataURL() only exists in the FMSv12 PHP API
                        $fieldData = $FM->getContainerDataURL($record->getField($fieldName, $repetition));
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
