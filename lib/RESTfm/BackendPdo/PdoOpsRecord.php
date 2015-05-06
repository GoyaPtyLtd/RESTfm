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

require_once 'PdoResponseException.php';

/**
 * PdoOpsRecord
 *
 * PHP PDO specific implementation of OpsRecordAbstract.
 */
class PdoOpsRecord extends OpsRecordAbstract {

    // --- OpsRecordAbstract implementation ---

    /**
     * Construct a new Record-level Operation object.
     *
     * @param BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     *  Unused - this is fixed in the PDO DSN.
     * @param string $uncleanTable
     *  Possibly malicious table name (this will be validated).
     */
    public function __construct (BackendAbstract $backend, $database, $uncleanTable) {
        $this->_backend = $backend;

        // Validate $uncleanTable by verifying it's existance in table list
        // provided by database. This is necessary as it is not possible to use
        // bound parameters for the table name in a query.
        $pdo = $this->_backend->getPDO();
        try {
            // MySQL:
            $result = $pdo->query('SHOW TABLES', PDO::FETCH_NUM);
        } catch (PDOException $e) {
            throw new PdoResponseException($e);
        }

        $this->_validatedTable = NULL;
        foreach ($result as $row) {
            if ($row[0] == $uncleanTable) {
                // Matched with db == valid.
                $this->_validatedTable = $uncleanTable;
                break;
            }
        }

        if ($this->_validatedTable === NULL) {
            throw new RESTfmResponseException(NULL, RESTfmResponseException::NOTFOUND);
        }
    }

    /**
     * Create a new record from the row data provided, recording the new
     * recordID (or failure) into the $restfmData object.
     *
     * Success will result in:
     *  - a new 'meta' section row containing a 'recordID' field.
     *
     * Failure will result in:
     *  - a new 'multistatus' row containing 'index', 'Status', and 'Reason'
     *
     * @param RESTfmDataSimple $restfmData
     *  Message object for operation success or failure.
     * @param integer $index
     *  Index for this row in original request. We don't have any other
     *  identifier for new record data.
     * @param array $row
     *  Associative array of fieldName => value pairs to create a new record
     *  from.
     */
    protected function _createRecord (RESTfmDataSimple $restfmData, $index, $row) {
        $pdo = $this->_backend->getPDO();

        /* FIXME:
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

        $columnNames = array();
        $bindValues = array();
        $valueList = array();
        foreach ($row as $fieldName => $fieldValue) {
            $this->_validateFieldName($fieldName);
            $columnNames[] = '`' . $fieldName . '`';
            $bindValues[] = '?';
            $valueList[] = $fieldValue;
        }
        $columnNamesStr = join(',', $columnNames);  // "`a`,`b`,..."
        $bindValuesStr = join(',', $bindValues);    // "?,?,..."

        $statement = $pdo->prepare('INSERT INTO `'. $this->_validatedTable . '` (' . $columnNamesStr .') VALUES (' . $bindValuesStr .  ')');
        try {
            $statement->execute($valueList);
        } catch (PDOException $e) {
            if ($this->_isSingle) {
                throw new PdoResponseException($e);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'index'         => $index,
                'Status'        => $e->getCode(),
                'Reason'        => $e->getMessage(),
            ));
            $statement->closeCursor();
            return;                                 // Nothing more to do here.
        }

        if ($this->_primaryKey !== NULL) {
            // Mysql:
            $recordID = $this->_primaryKey . '===' . $pdo->lastInsertId();
            $restfmData->pushDataRow(NULL, $recordID);
        }

        $statement->closeCursor();

        /* Deprecated:
        // Find the recordID from the sql insert.
        // MySQL:
        $statement = $pdo->prepare('SELECT LAST_INSERT_ID()');
        try {
            $statement->execute();
        } catch (PDOException $e) {
            throw new PdoResponseException($e);
        }
        $result = $statement->fetch(PDO::FETCH_NUM);
        $recordID = $result[0];
        $restfmData->pushDataRow(NULL, $recordID);
        $statement->closeCursor();
        */

    }

    /**
     * Read the record specified by $recordID into the $restfmData object.
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
     * @param RESTfmDataSimple $restfmData
     *  Destination for retrieved data.
     * @param string $recordID
     *  String containing record ID to retrieve.
     */
    protected function _readRecord (RESTfmDataSimple $restfmData, $recordID) {
        $pdo = $this->_backend->getPDO();

        // PDO backend can only support unique-key-recordID.
        if (strpos($recordID, '===') === FALSE) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new RESTfmResponseException('Invalid recordID, Not found', RESTfmResponseException::NOTFOUND);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => RESTfmResponseException::NOTFOUND,
                'Reason'        => 'Invalid recordID, Not found',
            ));
            return;                         // Nothing more to do here.
        }

        // Prepare query, limiting the results to a maximum of two records.
        list($searchField, $searchValue) = explode('===', $recordID, 2);
        $this->_validateFieldName($searchField);
        $statement = $pdo->prepare('SELECT * FROM `'. $this->_validatedTable . '` WHERE `' . $searchField . '` = ? LIMIT 2');
        try {
            $statement->execute(array($searchValue));
        } catch (PDOException $e) {
            if ($this->_isSingle) {
                throw new PdoResponseException($e);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => $result->getCode(),
                'Reason'        => $result->getMessage(),
            ));
            $statement->closeCursor();
            return;                         // Nothing more to do here.
        }

        // Parse the first record in $statement.
        $fetchCount = $this->_parseSingleRecord($restfmData, $recordID, $statement);

        // Check if there is more than one record returned.
        if ($statement->fetch() !== FALSE) {
            $fetchCount++;
        }

        $statement->closeCursor();

        if ($fetchCount === 0) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new RESTfmResponseException(NULL, RESTfmResponseException::NOTFOUND);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => RESTfmResponseException::NOTFOUND,
                'Reason'        => 'Not found',
            ));
            return;                         // Nothing more to do here.
        }

        if ($fetchCount > 1) {
            // We have to abort if the search query recordID is not unique.
            if ($this->_isSingle) {
                throw new RESTfmResponseException('Conflicting records found', RESTfmResponseException::CONFLICT);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => RESTfmResponseException::CONFLICT,
                'Reason' => $fetchCount . ' conflicting records found',
            ));
            return;                         // Nothing more to do here.
        }


        // All good.
    }

    /**
     * Update an existing record from the recordID and row data provided.
     * Recording failures into the $restfmData object.
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
     * @param RESTfmDataSimple $restfmData
     *  Message object for operation success or failure.
     * @param string $recordID
     *  Existing recordID to write $row data into.
     * @param integer $index
     *  Index for this row in original request. Only necessary for errors
     *  arising from _updateElseCreate flag.
     * @param array $row
     *  Associative array of fieldName => value pairs to create a new record
     *  from.
     */
    protected function _updateRecord (RESTfmDataSimple $restfmData, $recordID, $index, $row) {

        // PDO backend can only support unique-key-recordID.
        if (strpos($recordID, '===') === FALSE) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new RESTfmResponseException('Invalid recordID, Not found', RESTfmResponseException::NOTFOUND);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => RESTfmResponseException::NOTFOUND,
                'Reason'        => 'Invalid recordID, Not found',
            ));
            return;                         // Nothing more to do here.
        }

        /* FIXME: Part of appending to existing data (see below).
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

            // Re-throw exception.
            throw $e;
        }

        // Check if we have a multistatus error.
        if ($existingRecord->sectionExists('multistatus')) {
            $readStatus = $existingRecord->getSectionData('multistatus', 0);

            // Check for 404 Not Found status.
            if ($readStatus['Status'] == RESTfmResponseException::NOTFOUND && $this->_updateElseCreate) {
                // No record matching this unique-key-recordID,
                // create new record instead.
                return $this->_createRecord($restfmData, $index, $row);
            }

            // Set status in our own multistatus.
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => $readStatus['Status'],
                'Reason'        => $readStatus['Reason'],
            ));
            return;                             // Nothing more to do here.
        }
        */

        $pdo = $this->_backend->getPDO();

        /* FIXME:
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
        */

        /* FIXME:
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
        $columnSets = array();
        $valueList = array();
        foreach ($row as $fieldName => $fieldValue) {
            $this->_validateFieldName($fieldName);
            $columnSets[] = '`' . $fieldName . '`=?';
            $valueList[] = $fieldValue;
        }
        $columnSetsStr = join(',', $columnSets);  // "`a`=?,`b`=?,..."

        list($searchField, $searchValue) = explode('===', $recordID, 2);
        $this->_validateFieldName($searchField);
        $statement = $pdo->prepare('UPDATE `'. $this->_validatedTable . '` SET ' . $columnSetsStr . ' WHERE `' .$searchField. '` = ?');
        $valueList[] = $searchValue;
        try {
            $statement->execute($valueList);
        } catch (PDOException $e) {
            if ($this->_isSingle) {
                throw new PdoResponseException($e);
            }
            // Store result codes in multistatus section
            $restfmData->setSectionData('multistatus', NULL, array(
                'index'         => $recordID,
                'Status'        => $e->getCode(),
                'Reason'        => $e->getMessage(),
            ));
            $statement->closeCursor();
            return;                                 // Nothing more to do here.
        }

        if ($statement->rowCount() == 0) {
            if ($this->_updateElseCreate) {
                // No record matching this recordID, create new record instead.
                return $this->_createRecord($restfmData, $index, $row);
            }
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new RESTfmResponseException(NULL, RESTfmResponseException::NOTFOUND);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => RESTfmResponseException::NOTFOUND,
                'Reason'        => 'Not found',
            ));
        }

        $statement->closeCursor();
    }

    /**
     * Delete the record specified by $recordID recording failures into the
     * $restfmData object.
     *
     * Failure will result in:
     *  - a new 'multistatus' row containing 'recordID', 'Status', and 'Reason'
     *    fields to hold the FileMaker status of the query.
     *
     * @param RESTfmDataSimple $restfmData
     *  Destination for retrieved data.
     * @param string $recordID
     *  String containing record ID to retrieve.
     */
    protected function _deleteRecord (RESTfmDataSimple $restfmData, $recordID) {
        $pdo = $this->_backend->getPDO();

        // PDO backend can only support unique-key-recordID.
        if (strpos($recordID, '===') === FALSE) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new RESTfmResponseException('Invalid recordID, Not found', RESTfmResponseException::NOTFOUND);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => RESTfmResponseException::NOTFOUND,
                'Reason'        => 'Invalid recordID, Not found',
            ));
            return;                         // Nothing more to do here.
        }

        /* FIXME:
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

        list($searchField, $searchValue) = explode('===', $recordID, 2);
        $this->_validateFieldName($searchField);
        $statement = $pdo->prepare('DELETE FROM `'. $this->_validatedTable . '` WHERE `' .$searchField. '` = ?');
        try {
            $statement->execute(array($searchValue));
        } catch (PDOException $e) {
            if ($this->_isSingle) {
                throw new PdoResponseException($e);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => $result->getCode(),
                'Reason'        => $result->getMessage(),
            ));
            $statement->closeCursor();
            return;                         // Nothing more to do here.
        }

        if ($statement->rowCount() == 0) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404.
                throw new RESTfmResponseException(NULL, RESTfmResponseException::NOTFOUND);
            }
            $restfmData->setSectionData('multistatus', NULL, array(
                'recordID'      => $recordID,
                'Status'        => RESTfmResponseException::NOTFOUND,
                'Reason'        => 'Not found',
            ));
        }

        $statement->closeCursor();
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
        $restfmData = new RESTfmDataSimple();

        /*
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
        */

        return $restfmData;
    }

    // --- Protected ---

    /**
     * @var string
     *  Table name.
     */
    protected $_validatedTable;

    /**
     * @var array
     *  Associative array of known field names, used for sanitisation.
     */
    protected $_knownFieldNames = array();

    /**
     * @var string
     *  Primary key field name.
     */
    protected $_primaryKey = NULL;

    /**
     * Parse field data and meta data out of the first record provided
     * by the PDO statement object into provided RESTfmData object.
     *
     * @param[out] RESTfmDataSimple $restfmData
     * @param[in] string $recordID
     * @param[in] PDOStatement $statement
     *
     * @return integer
     *  Number of records parsed (0 or 1).
     */
    protected function _parseSingleRecord(RESTfmDataSimple $restfmData, $recordID, PDOStatement $statement) {
        // Only extract field meta data if we haven't done it yet.
        if ($restfmData->sectionExists('metaField') !== TRUE) {
            $numColumns = $statement->columnCount();
            for ($i=0; $i < $numColumns; $i++) {
                $allFieldMeta = $statement->getColumnMeta($i);
                $fieldName = $allFieldMeta['name'];

                // Keep only required fields.
                $requiredFields = array('native_type', 'flags', 'len', 'precision');
                $fieldMeta = array();
                foreach($requiredFields as $requiredField) {
                    if ($requiredField == 'flags') {
                        // Flags field is an array, not a string.
                        $fieldMeta[$requiredField] = join(', ', $allFieldMeta[$requiredField]);
                    } else {
                        $fieldMeta[$requiredField] = $allFieldMeta[$requiredField];
                    }
                }

                $restfmData->pushFieldMeta($fieldName, $fieldMeta);
            }
        }

        // Fetch first record.
        $record = $statement->fetch();
        if ($record === FALSE) {
            // No record returned.
            return 0;
        }

        $restfmData->pushDataRow($record, $recordID);
        return 1;
    }

    /**
     * Validate the provided fieldName. A fieldName not known to the table
     * in the database will cause an exception. Failure to validate a
     * fieldName will likely open up an SQL injection vulnerability.
     *
     * @param string $fieldName
     *
     * @return boolean
     *  TRUE on success.
     *
     * @throws RESTfmResponseException
     *  On failure to validate provided $fieldName.
     *
     * @throws PDOException
     *  On failure to retrieve field/column names from database.
     */
    protected function _validateFieldName ($fieldName) {
        if (empty($this->_knownFieldNames)) {
            // Need to fetch a single record to populate this array.
            $pdo = $this->_backend->getPDO();
            $statement = $pdo->prepare('SELECT * FROM `'. $this->_validatedTable . '` LIMIT 1');
            try {
                $statement->execute();
            } catch (PDOException $e) {
                throw new PdoResponseException($e);
            }

            // Populate _knownFieldNames.
            $numColumns = $statement->columnCount();
            for ($i=0; $i < $numColumns; $i++) {
                $allFieldMeta = $statement->getColumnMeta($i);
                $this->_knownFieldNames[$allFieldMeta['name']] = TRUE;

                // While we are at it, we will identify any primary_key.
                // Mysql:
                if ($this->_primaryKey === NULL && in_array('primary_key', $allFieldMeta['flags'])) {
                    $this->_primaryKey = $allFieldMeta['name'];
                }
            }

            $statement->closeCursor();
        }

        if (isset($this->_knownFieldNames[$fieldName])) {
            return TRUE;
        }

        error_log('RESTfm PdoOpsRecord::_validateFieldName error: Invalid field name: ' . $fieldName);
        throw new RESTfmResponseException('Invalid field name', RESTfmResponseException::INTERNALSERVERERROR);
    }

};
