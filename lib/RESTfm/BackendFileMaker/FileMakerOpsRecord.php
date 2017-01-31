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

/**
 * FileMakerOpsRecord
 *
 * FileMaker specific implementation of OpsRecordAbstract.
 */
class FileMakerOpsRecord extends OpsRecordAbstract {

    // --- OpsRecordAbstract implementation ---

    /**
     * Construct a new Record-level Operation object.
     *
     * @param BackendAbstract $backend
     * @param string $database
     * @param string $layout
     */
    public function __construct (BackendAbstract $backend, $database, $layout) {
        $this->_backend = $backend;
        $this->_database = $database;
        $this->_layout = $layout;
    }

    /**
     * Create a new record from the record provided, recording the new
     * recordID (or failure) into the $restfmMessage object.
     *
     * Success will result in:
     *  - a new 'meta' section row containing a 'recordID' field.
     *
     * Failure will result in:
     *  - a new 'multistatus' row containing 'index', 'Status', and 'Reason'
     *
     * @param RESTfmMessage $restfmMessage
     *  Message object for operation success or failure.
     * @param RESTfmMessageRecord $requestRecord
     *  Record containing row data.
     * @param integer $index
     *  Index for this row in original request. We don't have any other
     *  identifier for new record data.
     */
    protected function _createRecord (RESTfmMessage $restfmMessage, RESTfmMessageRecord $requestRecord, $index) {
        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        $valuesRepetitions = $this->_convertValuesToRepetitions($requestRecord);

        $addCommand = $FM->newAddCommand($this->_layout, $valuesRepetitions);

        // Script calling.
        if ($this->_postOpScriptTrigger) {
            $addCommand->setScript($this->_postOpScript, $this->_postOpScriptParameter);
            $this->_postOpScriptTrigger = FALSE;
        }
        if ($this->_preOpScriptTrigger) {
            $addCommand->setPreCommandScript($this->_preOpScript, $this->_preOpScriptParameter);
            $this->_preOpScriptTrigger = FALSE;
        }

        // Commit to database.
        // NOTE: We add the '@' to suppress PHP warnings in the FileMaker
        //       PHP API when non-existent fields are provided. We still catch
        //       the error OK.
        $result = @ $addCommand->execute();

        if (FileMaker::isError($result)) {
            if ($this->_isSingle) {
                throw new FileMakerResponseException($result);
            }
            $restfmMessage->addMultistatus(new RESTfmMessageMultistatus(
                    $result->getCode(),
                    $result->getMessage(),
                    $index
            ));
            return;                                 // Nothing more to do here.
        }

        // Query the result for the created records.
        $record = NULL;     // @var FileMaker_Record
        foreach ($result->getRecords() as $record) {
            if ($this->_suppressData) {
                // Insert just the recordID into the 'meta' section.
                $restfmMessage->addRecord(new RESTfmMessageRecord(
                        $record->getRecordId()
                ));
            } else {
                // Parse full record.
                $this->_parseRecord($restfmMessage, $record);
            }
        }
    }

    /**
     * Read the record specified by $requestRecord into the $restfmMessage
     * object.
     *
     * Success will result in:
     *  - a new 'data' row containing the retrieved record data.
     *  - a new 'meta' section row containing a 'recordID' field.
     *    Note: The index of the 'data' and 'meta' rows is always the same.
     *  - The 'metaField' section is created if it does not yet exist.
     *
     * Failure will result in:
     *  - a new 'multistatus' row containing 'recordID', 'Status', and 'Reason'
     *    fields to hold the FileMaker status of the query.
     *
     * @param RESTfmMessage $restfmMessage
     *  Destination for retrieved data.
     * @param RESTfmMessageRecord $requestRecord
     *  Record containing recordID to retrieve.
     */
    protected function _readRecord (RESTfmMessage $restfmMessage, RESTfmMessageRecord $requestRecord) {
        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        $recordID = $requestRecord->getRecordId();

        // Handle unique-key-recordID OR literal recordID.
        $record = NULL;
        if (strpos($recordID, '=')) {
            list($searchField, $searchValue) = explode('=', $recordID, 2);
            $findCommand = $FM->newFindCommand($this->_layout);
            $findCommand->addFindCriterion($searchField, $searchValue);
            $result = $findCommand->execute();

            if (FileMaker::isError($result)) {
                if ($this->_isSingle) {
                    if ($result->getCode() == 401) {
                        // "No records match the request"
                        // This is a special case where we actually want to return
                        // 404. ONLY because we are a unique-key-recordID.
                        throw new RESTfmResponseException(NULL, RESTfmResponseException::NOTFOUND);
                    } else {
                        throw new FileMakerResponseException($result);
                    }
                }
                $restfmMessage->addMultistatus(new RESTfmMessageMultistatus(
                    $result->getCode(),
                    $result->getMessage(),
                    $recordID
                ));
                return;                         // Nothing more to do here.
            }

            if ($result->getFetchCount() > 1) {
                // We have to abort if the search query recordID is not unique.
                if ($this->_isSingle) {
                    throw new RESTfmResponseException($result->getFetchCount() .
                            ' conflicting records found', RESTfmResponseException::CONFLICT);
                }
                $restfmMessage->addMultistatus(new RESTfmMessageMultistatus(
                    42409,                      // Made up status value.
                                                // 42xxx not in use by FileMaker
                                                // 409 Conflict is HTTP code.
                    $result->getFetchCount() . ' conflicting records found',
                    $recordID

                ));
                return;                         // Nothing more to do here.
            }

            $record = $result->getFirstRecord();
        } else {
            $record = $FM->getRecordById($this->_layout, $recordID);

            if (FileMaker::isError($record)) {
                if ($this->_isSingle) {
                    throw new FileMakerResponseException($record);
                }
                // Store result codes in multistatus section
                $restfmMessage->addMultistatus(new RESTfmMessageMultistatus(
                    $result->getCode(),
                    $result->getMessage(),
                    $recordID
                ));
                return;                             // Nothing more to do here.
            }
        }

        $this->_parseRecord($restfmMessage, $record);
    }

    /**
     * Update an existing record from the record provided.
     * Recording failures into the $restfmMessage object.
     *
     * If the _updateElseCreate flag is set, we will create a record if the
     * provided recordID does not exist.
     *
     * Success will result in:
     *  - Iff a new record is created, a new 'meta' section row containing
     *    a 'recordID' field.
     *
     * Failure will result in:
     *  - Iff a recordID exists, a new 'multistatus' row containing 'recordID',
     *    'Status', and 'Reason'.
     *  - Iff a recordID does not exist, a new 'multistatus' row containing
     *    'index', 'Status', and 'Reason'.
     *
     * @param RESTfmMessage $restfmMessage
     *  Message object for operation success or failure.
     * @param RESTfmMessageRecord $requestRecord
     *  Must contain row data and recordID
     * @param integer $index
     *  Index for this record in original request. Only necessary for errors
     *  arising from _updateElseCreate flag.
     */
    protected function _updateRecord (RESTfmMessage $restfmMessage, RESTfmMessageRecord $requestRecord, $index) {
        $realRecordID = $recordID;

        $existingRecord = NULL;
        if (strpos($recordID, '=')) {       // This is a unique-key-recordID
            $existingRecord = new RESTfmDataSimple();

            // $this->_readRecord() will throw an exception if $this->_isSingle.
            try {
                $this->_readRecord($existingRecord, $recordID);
            } catch (RESTfmResponseException $e) {
                // Check for 404 Not Found in exception.
                if ($e->getCode() == RESTfmResponseException::NOTFOUND && $this->_updateElseCreate) {
                    // No record matching this unique-key-recordID,
                    // create new record instead.
                    return $this->_createRecord($restfmData, $index, $row);
                }

                // Not 404, re-throw exception.
                throw $e;
            }

            // Check if we have a multistatus error.
            if ($existingRecord->sectionExists('multistatus')) {
                $readStatus = $existingRecord->getSectionData('multistatus', 0);

                // Check for FileMaker error 401: No records match the request
                if ($readStatus['Status'] == 401 && $this->_updateElseCreate) {
                    // No record matching this unique-key-recordID,
                    // create new record instead.
                    return $this->_createRecord($restfmData, $index, $row);
                }

                // Some other error, set status in our own multistatus.
                $restfmData->setSectionData('multistatus', NULL, array(
                    'recordID'      => $recordID,
                    'Status'        => $readStatus['Status'],
                    'Reason'        => $readStatus['Reason'],
                ));
                return;                             // Nothing more to do here.
            }

            // We need the first element of the 'meta' section. The
            // index will be the recordID we are trying to find.
            $existingRecord->setIteratorSection('meta');
            $existingRecord->rewind();
            $realRecordID = $existingRecord->key();
        }

        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        // Allow appending to existing data.
        if ($this->_updateAppend) {
            if ($existingRecord == NULL) {
                $existingRecord = new RESTfmDataSimple();
                $this->_readRecord($existingRecord, $recordID);

                // Check if we have an error.
                if ($existingRecord->sectionExists('multistatus')) {
                    $readStatus = $existingRecord->getSectionData('multistatus', 0);
                    // Set status in our own multistatus.
                    $restfmData->setSectionData('multistatus', NULL, array(
                        'recordID'      => $recordID,
                        'Status'        => $readStatus['Status'],
                        'Reason'        => $readStatus['Reason'],
                    ));
                    return;                         // Nothing more to do here.
                }
            }

            // We need the first element of the 'data' section.
            $existingRecord->setIteratorSection('data');
            $existingRecord->rewind();
            $existingRow = $existingRecord->current();

            foreach ($row as $fieldName => $value) {
                $row[$fieldName] = $existingRow[$fieldName] . $value;
            }
        }

        $updatedValuesRepetitions = $this->_convertValuesToRepetitions($row);

        // New edit command on record with values to update.
        $editCommand = $FM->newEditCommand($this->_layout, $realRecordID, $updatedValuesRepetitions);

        // Script calling.
        if ($this->_postOpScriptTrigger) {
            $editCommand->setScript($this->_postOpScript, $this->_postOpScriptParameter);
            $this->_postOpScriptTrigger = FALSE;
        }
        if ($this->_preOpScriptTrigger) {
            $editCommand->setPreCommandScript($this->_preOpScript, $this->_preOpScriptParameter);
            $this->_preOpScriptTrigger = FALSE;
        }

        // Commit edit back to database.
        // NOTE: We add the '@' to suppress PHP warnings in the FileMaker
        //       PHP API when non-existent fields are provided. We still catch
        //       the error OK.
        $result = @ $editCommand->execute();
        if (FileMaker::isError($result)) {
            // Check for FileMaker error 401: No records match the request
            if ($result->getCode() == 401 && $this->_updateElseCreate) {
                // No record matching this recordID, create new record instead.
                return $this->_createRecord($restfmData, $index, $row);
            }

            if ($this->_isSingle) {
                throw new FileMakerResponseException($result);
            }
            // Store result codes in multistatus section
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => $result->getCode(),
                'Reason'        => $result->getMessage(),
            ));
            return;                                 // Nothing more to do here.
        }
    }

    /**
     * Delete the record specified, recording failures into the
     * $restfmMessage object.
     *
     * Failure will result in:
     *  - a new 'multistatus' row containing 'recordID', 'Status', and 'Reason'
     *    fields to hold the FileMaker status of the query.
     *
     * @param RESTfmMessage $restfmMessage
     *  Destination for retrieved data.
     * @param RESTfmMessageRecord $requestRecord
     *  Record containing recordID to delete.
     */
    protected function _deleteRecord (RESTfmMessage $restfmMessage, RESTfmMessageRecord $requestRecord) {
        $realRecordID = $recordID;

        $existingRecord = NULL;
        if (strpos($recordID, '=')) {       // This is a unique-key-recordID
            $existingRecord = new RESTfmDataSimple();
            $this->_readRecord($existingRecord, $recordID);

            // Check if we have an error.
            if ($existingRecord->sectionExists('multistatus')) {
                $readStatus = $existingRecord->getSectionData('multistatus', 0);
                // Set status in our own multistatus.
                $restfmData->setSectionData('multistatus', NULL, array(
                    'recordID'      => $recordID,
                    'Status'        => $readStatus['Status'],
                    'Reason'        => $readStatus['Reason'],
                ));
                return;                             // Nothing more to do here.
            }

            // We need the first element of the 'meta' section. The
            // index will be the recordID we are trying to find.
            $existingRecord->setIteratorSection('meta');
            $existingRecord->rewind();
            $realRecordID = $existingRecord->key();
        }

        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        $deleteCommand = $FM->newDeleteCommand($this->_layout, $realRecordID);

        // Script calling.
        if ($this->_postOpScriptTrigger) {
            $deleteCommand->setScript($this->_postOpScript, $this->_postOpScriptParameter);
            $this->_postOpScriptTrigger = FALSE;
        }
        if ($this->_preOpScriptTrigger) {
            $deleteCommand->setPreCommandScript($this->_preOpScript, $this->_preOpScriptParameter);
            $this->_preOpScriptTrigger = FALSE;
        }

        $result = $deleteCommand->execute();

        if (FileMaker::isError($result)) {
            if ($this->_isSingle) {
                throw new FileMakerResponseException($result);
            }
            // Store result codes in multistatus section
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => $result->getCode(),
                'Reason'        => $result->getMessage(),
            ));
            return;                                 // Nothing more to do here.
        }
    }

    /**
     * Call a script in the context of this layout.
     *
     * @param string $scriptName
     * @param string $scriptParameter
     *  Optional parameter to pass to script.
     *
     * @throws RESTfmResponseException
     *  On error
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta', 'metaField' sections.
     *  - does not contain 'multistatus' this is not a bulk operation.
     */
    public function callScript ($scriptName, $scriptParameter = NULL) {
        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        $restfmData = new RESTfmDataSimple();

        // FileMaker only supports passing a single string parameter into a
        // script. Any requirements for multiple parameters must be handled
        // by string processing within the script.
        $scriptCommand = $FM->newPerformScriptCommand($this->_layout, $scriptName, $scriptParameter);

        // NOTE: We add the '@' to suppress PHP warnings in the FileMaker
        //       PHP API when non variable references are returned. We still
        //       catch the error OK.
        @ $result = $scriptCommand->execute();

        if (FileMaker::isError($result)) {
            throw new FileMakerResponseException($result);
        }

        // Something a bit weird here. Every call to newPerformScriptCommand()
        // will return at least one row of records, even if the script does not
        // perform a find.
        // The record appears random.

        // Query the result for returned records.
        if (! $this->_suppressData) {
            foreach ($result->getRecords() as $record) {
                $this->_parseRecord($restfmData, $record);
            }
        }

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
     * Parse FileMaker record into RESTfmMessage format.
     *
     * @param[out] RESTfmMessage $restfmMessage
     * @param[in] FileMaker_Record $record
     */
    protected function _parseRecord (RESTfmMessage $restfmMessage, FileMaker_Record $record) {
        $fieldNames = $record->getFields();

        // Only extract field meta data if we haven't done it yet.
        if ($restfmMessage->getMetaFieldCount() < 1) {
            // Dig out field meta data from field objects in layout object
            // returned by record object!
            $layoutResult = $record->getLayout();
            foreach ($fieldNames as $fieldName) {
                $fieldResult = $layoutResult->getField($fieldName);

                $restfmMessageRow = new RESTfmMessageRow();

                $restfmMessageRow['name'] = $fieldName;
                $restfmMessageRow['autoEntered'] = $fieldResult->isAutoEntered() ? 1 : 0;
                $restfmMessageRow['global'] = $fieldResult->isGlobal() ? 1 : 0;
                $restfmMessageRow['maxRepeat'] = $fieldResult->getRepetitionCount();
                $restfmMessageRow['resultType'] = $fieldResult->getResult();
                //$restfmMessageRow['type'] = $fieldResult->getType();

                $restfmMessage->setMetaField($fieldName, $restfmMessageRow);
            }
        }

        $FM = $this->_backend->getFileMaker();
        $FM->setProperty('database', $this->_database);

        // Process record and store data.
        $metaFields = $restfmMessage->getMetaFields();
        $restfmMessageRecord = new RESTfmMessageRecord($record->getRecordId());
        foreach ($fieldNames as $fieldName) {
            $metaFieldRow = NULL; // @var RESTfmMessageRow
            $metaFieldRow = $metaFields[$fieldName];

            // Field repetitions are expanded into multiple fields with
            // an index operator suffix; fieldName[0], fieldName[1] ...
            $fieldRepeat = $metaFieldRow['maxRepeat'];
            for ($repetition = 0; $repetition < $fieldRepeat; $repetition++) {
                $fieldNameRepeat = $fieldName;

                // Apply index suffix only when more than one $fieldRepeat.
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
                $restfmMessageRecord[$fieldNameRepeat] = $fieldData;
            }
        }
        $restfmMessage->addRecord($restfmMessageRecord);
    }

    /**
     * Convert an associative array of fieldName => value pairs, where
     * repetitions are expressed as "fieldName[numericalIndex]" => "value",
     * into the form "fieldName" => array( numericalIndex => "value", ... )
     * i.e. convert from "RESTfmData format" into "FileMaker add/edit $values
     * format".
     *
     * @param Array $values
     *  Associative array of fieldName => value pairs.
     *
     * @return Array
     *  Associative array where repetitions are converted into a format
     *  suitable for $values parameter of FileMaker API add/edit functions.
     */
    protected function _convertValuesToRepetitions ($values) {
        // Reprocess $values for repetitions compatibility.
        //
        // FileMaker::newAddCommand() / FileMaker::newEditCommand() state
        // that $values / $updatedValues, which contain fieldName => value
        // pairs, should supply a numerically indexed array for the value of
        // any fields with repetitions.
        //
        // The obfuscated constructor of AddImpl.php / EditImpl.php shows
        // that it converts all non-array values into single element arrays
        // internally. This also verifies that the array index must start at
        // zero.
        $valuesRepetitions = array();
        foreach ($values as $fieldName => $value) {
            $matches = array();
            if (preg_match('/^(.+)\[(\d+)\]$/', $fieldName, $matches)) {
                $fieldName = $matches[1];   // Real fieldName minus index.
                $repetition = intval($matches[2]);

                // Use existing array, else construct a new one.
                if ( isset($valuesRepetitions[$fieldName]) &&
                        is_array($valuesRepetitions[$fieldName]) ) {
                    $repeatArray = $valuesRepetitions[$fieldName];
                } else {
                    $repeatArray = array();
                }

                $repeatArray[$repetition] = $value;
                $valuesRepetitions[$fieldName] = $repeatArray;
            } else {
                $valuesRepetitions[$fieldName] = $value;
            }
        }

        return $valuesRepetitions;
    }

};
