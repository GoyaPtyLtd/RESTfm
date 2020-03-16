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
class OpsRecord extends \RESTfm\OpsRecordAbstract {

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
     * Create a new record from the record provided, recording the new
     * recordID (or failure) into the $restfmMessage object.
     *
     * Success will result in:
     *  - a new 'meta' section row containing a 'recordID' field.
     *
     * Failure will result in:
     *  - a new 'multistatus' row containing 'index', 'Status', and 'Reason'
     *
     * @param \RESTfm\Message\Message $restfmMessage
     *  Message object for operation success or failure.
     * @param \RESTfm\Message\Record $requestRecord
     *  Record containing row data.
     * @param integer $index
     *  Index for this row in original request. We don't have any other
     *  identifier for new record data.
     */
    protected function _createRecord (\RESTfm\Message\Message $restfmMessage, \RESTfm\Message\Record $requestRecord, $index) {
        $fmDataApi = $this->_backend->getFileMakerDataApi(); // @var FileMakerDataApi

        /*
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
        */

        // Commit to database.
        $result = $fmDataApi->createRecord($this->_layout, $requestRecord->_getDataReference());

        if ($result->isError()) {
            if ($this->_isSingle) {
                throw new FileMakerDataApiResponseException($result);
            }
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                    $result->getCode(),
                    $result->getMessage(),
                    $index
            ));
            return;                                 // Nothing more to do here.
        }

        // Insert just the recordID into the 'meta' section.
        $restfmMessage->addRecord(new \RESTfm\Message\Record(
                $result->getRecordId()
        ));
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
     * @param \RESTfm\Message\Message $restfmMessage
     *  Destination for retrieved data.
     * @param \RESTfm\Message\Record $requestRecord
     *  Record containing recordID to retrieve.
     */
    protected function _readRecord (\RESTfm\Message\Message $restfmMessage, \RESTfm\Message\Record $requestRecord) {
        $fmDataApi = $this->_backend->getFileMakerDataApi(); // @var FileMakerDataApi

        $recordID = $requestRecord->getRecordId();

        // Handle unique-key-recordID OR literal recordID.
        $record = NULL;
        if (strpos($recordID, '=')) {
            list($searchField, $searchValue) = explode('=', $recordID, 2);
            $query = array( array( $searchField => $searchValue ) );
            $result = $fmDataApi->findRecords($this->_layout, $query);

            if ($result->isError()) {
                if ($this->_isSingle) {
                    if ($result->getCode() == 401) {
                        // "No records match the request"
                        // This is a special case where we actually want to return
                        // 404. ONLY because we are a unique-key-recordID.
                        throw new \RESTfm\ResponseException(NULL, \RESTfm\ResponseException::NOTFOUND);
                    } else {
                        throw new FileMakerDataApiResponseException($result);
                    }
                }
                $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                    $result->getCode(),
                    $result->getMessage(),
                    $recordID
                ));
                return;                         // Nothing more to do here.
            }

            if ($result->getFetchCount() > 1) {
                // We have to abort if the search query recordID is not unique.
                if ($this->_isSingle) {
                    throw new \RESTfm\ResponseException($result->getFetchCount() .
                            ' conflicting records found', \RESTfm\ResponseException::CONFLICT);
                }
                $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                    42409,                      // Made up status value.
                                                // 42xxx not in use by FileMaker
                                                // 409 Conflict is HTTP code.
                    $result->getFetchCount() . ' conflicting records found',
                    $recordID

                ));
                return;                         // Nothing more to do here.
            }

        } else {
            $result = $fmDataApi->getRecord($this->_layout, $recordID); // @var FileMakerDataApiResult

            if ($result->isError()) {
                if ($this->_isSingle) {
                    throw new FileMakerDataApiResponseException($result);
                }
                // Store result codes in multistatus section
                $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                    $result->getCode(),
                    $result->getMessage(),
                    $recordID
                ));
                return;                         // Nothing more to do here.
            }
        }

        $record = $result->getFirstRecord();
        $restfmMessage->addRecord(new \RESTfm\Message\Record(
            $record['recordId'],
            NULL,
            $record['fieldData']
        ));

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
     * @param \RESTfm\Message\Message $restfmMessage
     *  Message object for operation success or failure.
     * @param \RESTfm\Message\Record $requestRecord
     *  Must contain row data and recordID
     * @param integer $index
     *  Index for this record in original request. Only necessary for errors
     *  arising from _updateElseCreate flag.
     */
    protected function _updateRecord (\RESTfm\Message\Message $restfmMessage, \RESTfm\Message\Record $requestRecord, $index) {
        $recordID = $requestRecord->getRecordId();

        $readMessage = NULL;            // May be re-used for appending data.

        if (strpos($recordID, '=')) {   // This is a unique-key-recordID, will
                                        // need to find the real recordID.
            $readMessage = new \RESTfm\Message\Message();

            // $this->_readRecord() will throw an exception if $this->_isSingle.
            try {
                $this->_readRecord($readMessage, new \RESTfm\Message\Record($recordID));
            } catch (\RESTfm\ResponseException $e) {
                // Check for 404 Not Found in exception.
                if ($e->getCode() == \RESTfm\ResponseException::NOTFOUND && $this->_updateElseCreate) {
                    // No record matching this unique-key-recordID,
                    // create new record instead.
                    return $this->_createRecord($restfmMessage, $requestRecord, $index);
                }

                // Not 404, re-throw exception.
                throw $e;
            }

            // Check if we have an error.
            $readStatus = $readMessage->getMultistatus(0);
            if ($readStatus !== NULL) {

                // Check for FileMaker error 401: No records match the request
                if ($readStatus->getStatus() == 401 && $this->_updateElseCreate) {
                    // No record matching this unique-key-recordID,
                    // create new record instead.
                    return $this->_createRecord($restfmMessage, $requestRecord, $index);
                }

                // Some other error, set our own multistatus.
                $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                        $readStatus->getStatus(),
                        $readStatus->getReason(),
                        $requestRecord->getRecordId()
                ));
                return;                             // Nothing more to do here.
            }

            // We now have the real recordID.
            $recordID = $readMessage->getRecord(0)->getRecordId();
        }

        $fmDataApi = $this->_backend->getFileMakerDataApi(); // @var FileMakerDataApi

        // Allow appending to existing data.
        if ($this->_updateAppend) {
            if ($readMessage == NULL) {
                $readMessage = new \RESTfm\Message\Message();
                $this->_readRecord($readMessage, new \RESTfm\Message\Record($recordID));

                // Check if we have an error.
                $readStatus = $readMessage->getMultistatus(0);
                if ($readStatus !== NULL) {
                    // Set our own multistatus for this error.
                    $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                            $readStatus->getStatus(),
                            $readStatus->getReason(),
                            $requestRecord->getRecordId()
                    ));
                    return;                             // Nothing more to do here.
                }
            }

            $readRecord = $readMessage->getRecord(0);

            // Rebuild $requestRecord field values by appending to the field
            // values in $readRecord.
            foreach ($requestRecord as $fieldName => $value) {
                $requestRecord[$fieldName] = $readRecord[$fieldName] . $value;
            }
        }

        /*
        $updatedValuesRepetitions = $this->_convertValuesToRepetitions($requestRecord);

        // New edit command on record with values to update.
        $editCommand = $FM->newEditCommand($this->_layout, $recordID, $updatedValuesRepetitions);

        // Script calling.
        if ($this->_postOpScriptTrigger) {
            $editCommand->setScript($this->_postOpScript, $this->_postOpScriptParameter);
            $this->_postOpScriptTrigger = FALSE;
        }
        if ($this->_preOpScriptTrigger) {
            $editCommand->setPreCommandScript($this->_preOpScript, $this->_preOpScriptParameter);
            $this->_preOpScriptTrigger = FALSE;
        }
        */

        // Commit edit back to database.
        $result = $fmDataApi->editRecord($this->_layout, $recordID, $requestRecord->_getDataReference());

        if ($result->isError()) {
            // Check for FileMaker error 401: No records match the request
            if ($result->getCode() == 401 && $this->_updateElseCreate) {
                // No record matching this recordID, create new record instead.
                return $this->_createRecord($restfmMessage, $requestRecord, $index);
            }

            if ($this->_isSingle) {
                throw new FileMakerDataApiResponseException($result);
            }
            // Store result codes in multistatus section
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                $result->getCode(),
                $result->getMessage(),
                $requestRecord->getRecordId()
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
     * @param \RESTfm\Message\Message $restfmMessage
     *  Destination for retrieved data.
     * @param \RESTfm\Message\Record $requestRecord
     *  Record containing recordID to delete.
     */
    protected function _deleteRecord (\RESTfm\Message\Message $restfmMessage, \RESTfm\Message\Record $requestRecord) {
        $recordID = $requestRecord->getRecordId();

        if (strpos($recordID, '=')) {   // This is a unique-key-recordID, will
                                        // need to find the real recordID.
            $readMessage = new \RESTfm\Message\Message();
            $this->_readRecord($readMessage, new \RESTfm\Message\Record($recordID));

            // Check if we have an error.
            $readStatus = $readMessage->getMultistatus(0);
            if ($readStatus !== NULL) {
                // Set our own multistatus for this error.
                $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                        $readStatus->getStatus(),
                        $readStatus->getReason(),
                        $requestRecord->getRecordId()
                ));
                return;                             // Nothing more to do here.
            }

            // We now have the real recordID.
            $recordID = $readMessage->getRecord(0)->getRecordId();
        }

        $fmDataApi = $this->_backend->getFileMakerDataApi(); // @var FileMakerDataApi

        /*
        // Script calling.
        if ($this->_postOpScriptTrigger) {
            $deleteCommand->setScript($this->_postOpScript, $this->_postOpScriptParameter);
            $this->_postOpScriptTrigger = FALSE;
        }
        if ($this->_preOpScriptTrigger) {
            $deleteCommand->setPreCommandScript($this->_preOpScript, $this->_preOpScriptParameter);
            $this->_preOpScriptTrigger = FALSE;
        }
        */

        $result = $fmDataApi->deleteRecord($this->_layout, $recordID);

        if ($result->isError()) {
            if ($this->_isSingle) {
                throw new FileMakerDataApiResponseException($result);
            }
            // Store result codes in multistatus section
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                $result->getCode(),
                $result->getMessage(),
                $requestRecord->getRecordId()
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
     * @throws \RESTfm\ResponseException
     *  On error
     *
     * @return \RESTfm\Message\Message
     *  - 'data', 'meta', 'metaField' sections.
     *  - does not contain 'multistatus' this is not a bulk operation.
     */
    public function callScript ($scriptName, $scriptParameter = NULL) {
        $fmDataApi = $this->_backend->getFileMakerDataApi(); // @var FileMakerDataApi

        // FileMaker only supports passing a single string parameter into a
        // script. Any requirements for multiple parameters must be handled
        // by string processing within the script.
        $result = $fmDataApi->executeScript($this->_layout, $scriptName, $scriptParameter);

        if ($result->isError()) {
            throw new FileMakerDataApiResponseException($result);
        }

        // Every FileMaker script will return a found set of at least on record,
        // even if the script does not perform a find. The record can be random.

        $restfmMessage = new \RESTfm\Message\Message();

        // Query the result for returned records.
        if (! $this->_suppressData) {
            foreach ($result->getRecords() as $record) {
                $restfmMessage->addRecord(new \RESTfm\Message\Record(
                    $record['recordId'],
                    NULL,
                    $record['fieldData']
                ));
            }
        }

        return $restfmMessage;
    }

};
