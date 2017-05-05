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

namespace RESTfm\BackendPdo;

/**
 * PdoOpsRecord
 *
 * PHP PDO specific implementation of OpsRecordAbstract.
 */
class PdoOpsRecord extends \RESTfm\OpsRecordAbstract {

    // --- OpsRecordAbstract implementation ---

    /**
     * Construct a new Record-level Operation object.
     *
     * @param \RESTfm\BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     *  Unused - this is fixed in the PDO DSN.
     * @param string $uncleanTable
     *  Possibly malicious table name (this will be validated).
     */
    public function __construct (\RESTfm\BackendAbstract $backend, $database, $uncleanTable) {
        $this->_backend = $backend;

        // Validate $uncleanTable by verifying it's existance in table list
        // provided by database. This is necessary as it is not possible to use
        // bound parameters for the table name in a query.
        $pdo = $this->_backend->getPDO();
        try {
            // MySQL:
            $result = $pdo->query('SHOW TABLES', \PDO::FETCH_NUM);
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
            throw new \RESTfm\ResponseException(NULL, \RESTfm\ResponseException::NOTFOUND);
        }
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
        foreach ($requestRecord as $fieldName => $fieldValue) {
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
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                    $e->getCode(),
                    $e->getMessage(),
                    $index
            ));
            $statement->closeCursor();
            return;                                 // Nothing more to do here.
        }

        if ($this->_primaryKey !== NULL) {
            // Mysql:
            $recordID = $this->_primaryKey . '===' . $pdo->lastInsertId();
            $restfmMessage->addRecord(new \RESTfm\Message\Record($recordID));
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
        $result = $statement->fetch(\PDO::FETCH_NUM);
        $recordID = $result[0];
        $restfmMessage->addRecord(new \RESTfm\Message\Record($recordID));
        $statement->closeCursor();
        */

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
        $pdo = $this->_backend->getPDO();

        $recordID = $requestRecord->getRecordId();

        // PDO backend can only support unique-key-recordID.
        if (strpos($recordID, '===') === FALSE) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new \RESTfm\ResponseException('Invalid recordID, Not found', \RESTfm\ResponseException::NOTFOUND);
            }
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                \RESTfm\ResponseException::NOTFOUND,
                'Invalid recordID, Not found',
                $recordID
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
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                    $e->getCode(),
                    $e->getMessage(),
                    $recordID
            ));
            $statement->closeCursor();
            return;                         // Nothing more to do here.
        }

        // Parse the first record in $statement.
        $fetchCount = $this->_parseSingleRecord($restfmMessage, $recordID, $statement);

        // Check if there is more than one record returned.
        if ($statement->fetch() !== FALSE) {
            $fetchCount++;
        }

        $statement->closeCursor();

        if ($fetchCount === 0) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new \RESTfm\ResponseException(NULL, \RESTfm\ResponseException::NOTFOUND);
            }
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                \RESTfm\ResponseException::NOTFOUND,
                'Not found',
                $recordID
            ));
            return;                         // Nothing more to do here.
        }

        if ($fetchCount > 1) {
            // We have to abort if the search query recordID is not unique.
            if ($this->_isSingle) {
                throw new \RESTfm\ResponseException('Conflicting records found', \RESTfm\ResponseException::CONFLICT);
            }
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                \RESTfm\ResponseException::CONFLICT,
                $fetchCount . ' conflicting records found',
                $recordID
            ));
            return;                         // Nothing more to do here.
        }


        // All good.
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

        // PDO backend can only support unique-key-recordID.
        if (strpos($recordID, '===') === FALSE) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new \RESTfm\ResponseException('Invalid recordID, Not found', \RESTfm\ResponseException::NOTFOUND);
            }
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                \RESTfm\ResponseException::NOTFOUND,
                'Invalid recordID, Not found',
                $recordID
            ));
            return;                         // Nothing more to do here.
        }

        $pdo = $this->_backend->getPDO();

        /* @TODO:
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
        foreach ($requestRecord as $fieldName => $fieldValue) {
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
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                    $e->getCode(),
                    $e->getMessage(),
                    $recordID
            ));
            $statement->closeCursor();
            return;                                 // Nothing more to do here.
        }

        if ($statement->rowCount() == 0) {
            if ($this->_updateElseCreate) {
                // No record matching this recordID, create new record instead.
                return $this->_createRecord($restfmMessage, $index, $row);
            }
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new \RESTfm\ResponseException(NULL, \RESTfm\ResponseException::NOTFOUND);
            }
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                \RESTfm\ResponseException::NOTFOUND,
                'Not found',
                $recordID
            ));
        }

        $statement->closeCursor();
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
        $pdo = $this->_backend->getPDO();

        $recordID = $requestRecord->getRecordId();

        // PDO backend can only support unique-key-recordID.
        if (strpos($recordID, '===') === FALSE) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404. ONLY because we are a unique-key-recordID.
                throw new \RESTfm\ResponseException('Invalid recordID, Not found', \RESTfm\ResponseException::NOTFOUND);
            }
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                \RESTfm\ResponseException::NOTFOUND,
                'Invalid recordID, Not found',
                $recordID
            ));
            return;                         // Nothing more to do here.
        }

        /* @TODO:
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
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                    $e->getCode(),
                    $e->getMessage(),
                    $recordID
            ));
            $statement->closeCursor();
            return;                         // Nothing more to do here.
        }

        if ($statement->rowCount() == 0) {
            if ($this->_isSingle) {
                // This is a special case where we actually want to return
                // 404.
                throw new \RESTfm\ResponseException(NULL, \RESTfm\ResponseException::NOTFOUND);
            }
            $restfmMessage->addMultistatus(new \RESTfm\Message\Multistatus(
                \RESTfm\ResponseException::NOTFOUND,
                'Not found',
                $recordID
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
     * @throws \RESTfm\ResponseException
     *  On error
     *
     * @return \RESTfm\Message\Message
     *  - 'data', 'meta', 'metaField' sections.
     *  - does not contain 'multistatus' this is not a bulk operation.
     */
    public function callScript ($scriptName, $scriptParameter = NULL) {
        $restfmMessage = new \RESTfm\Message\Message();

        /*
        // FileMaker only supports passing a single string parameter into a
        // script. Any requirements for multiple parameters must be handled
        // by string processing within the script.
        $scriptCommand = $FM->newPerformScriptCommand($this->_layout, $scriptName, $scriptParameter);

        // NOTE: We add the '@' to suppress PHP warnings in the FileMaker
        //       PHP API when non variable references are returned. We still
        //       catch the error OK.
        @ $result = $scriptCommand->execute();

        if (\FileMaker::isError($result)) {
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

        return $restfmMessage;
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
     * by the PDO statement object into provided \RESTfm\Message\Message object.
     *
     * @param[out] \RESTfm\Message\Message $restfmMessage
     * @param[in] string $recordID
     * @param[in] PDOStatement $statement
     *
     * @return integer
     *  Number of records parsed (0 or 1).
     */
    protected function _parseSingleRecord(\RESTfm\Message\Message $restfmMessage, $recordID, \PDOStatement $statement) {
        // Only extract field meta data if we haven't done it yet.
        if ($restfmMessage->getMetaFieldCount() < 1) {
            $numColumns = $statement->columnCount();
            for ($i=0; $i < $numColumns; $i++) {
                $allFieldMeta = $statement->getColumnMeta($i);
                $fieldName = $allFieldMeta['name'];

                // Keep only required fields.
                $requiredFields = array('native_type', 'flags', 'len', 'precision');
                $restfmMessageRow = new \RESTfm\Message\Row();
                foreach($requiredFields as $requiredField) {
                    if ($requiredField == 'flags') {
                        // Flags field is an array, not a string.
                        $restfmMessageRow[$requiredField] = join(', ', $allFieldMeta[$requiredField]);
                    } else {
                        $restfmMessageRow[$requiredField] = $allFieldMeta[$requiredField];
                    }
                }

                $restfmMessage->setMetaField($fieldName, $restfmMessageRow);
            }
        }

        // Fetch first record.
        $record = $statement->fetch();
        if ($record === FALSE) {
            // No record returned.
            return 0;
        }

        $restfmMessage->addRecord(new \RESTfm\Message\Record($recordID, NULL, $record));
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
     * @throws \RESTfm\ResponseException
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
        throw new \RESTfm\ResponseException('Invalid field name', \RESTfm\ResponseException::INTERNALSERVERERROR);
    }

};
