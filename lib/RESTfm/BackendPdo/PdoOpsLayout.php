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
 * PdoOpsLayout
 *
 * PHP PDO specific implementation of OpsLayoutAbstract.
 */
class PdoOpsLayout extends OpsLayoutAbstract {

    // --- OpsRecordLayout implementation ---

    /**
     * Construct a new Layout-level Operation object.
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

        // Validate $uncleanTable by verifying it's existence in table list
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
     * Read records in table in database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta', 'metaField' sections.
     */
    public function read () {
        $pdo = $this->_backend->getPDO();

        /* TODO: selection criteria.
        // New FileMaker find command.
        if (count($this->_findCriteria) > 0) {
            // This search query will contain criterion.
            $findCommand = $FM->newFindCommand($this->_layout);
            foreach ($this->_findCriteria as $fieldName => $testValue) {
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
        */

        $findSkip = $this->_readOffset;
        $findMax = $this->_readCount;

        // Find the number of records in this table.
        // This may not be a cheap operation, may be ok in some databases.
        $statement = $pdo->prepare('SELECT COUNT(*) FROM `'. $this->_validatedTable);
        try {
            $statement->execute();
        } catch (PDOException $e) {
            throw new PdoResponseException($e);
        }
        $result = $statement->fetch(PDO::FETCH_NUM);
        $tableRecordCount = $result[0];
        $statement->closeCursor();

        if ($findSkip == -1) {
            // If we are to skip to the end ...
            $findSkip = $tableRecordCount - $findMax;
            $findSkip = max(0, $findSkip);  // Ensure not less than zero.
        }

        // Query.
        $statement = $pdo->prepare('SELECT * FROM `'. $this->_validatedTable . '` LIMIT ? OFFSET ?');
        $statement->bindParam(1, intval($findMax), PDO::PARAM_INT);
        $statement->bindParam(2, intval($findSkip), PDO::PARAM_INT);
        try {
            $statement->execute();
        } catch (PDOException $e) {
            throw new PdoResponseException($e);
        }

        $restfmData = new RESTfmDataSimple();

        $this->_parseMetaField($restfmData, $statement);

        $fetchCount = 0;
        foreach ($statement->fetchAll() as $record) {
            if ($this->_uniqueKey === NULL) {
                $recordID = NULL;
            } else {
                $recordID = $this->_uniqueKey . '===' . $record[$this->_uniqueKey];
            }

            $restfmData->pushDataRow($record, $recordID, NULL);
            $fetchCount++;
        }

        $statement->closeCursor();

        // Info.
        $restfmData->pushInfo('tableRecordCount', $tableRecordCount);
        $restfmData->pushInfo('foundSetCount', $tableRecordCount);
        $restfmData->pushInfo('fetchCount', $fetchCount);

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
        $pdo = $this->_backend->getPDO();

        $statement = $pdo->prepare('SELECT * FROM `'. $this->_validatedTable . '` LIMIT 1 OFFSET 0');
        try {
            $statement->execute();
        } catch (PDOException $e) {
            throw new PdoResponseException($e);
        }

        $restfmData = new RESTfmDataSimple();

        $this->_parseMetaField($restfmData, $statement);

        $statement->closeCursor();

        return $restfmData;
    }

    // --- Protected ---

    /**
     * @var string
     *  Table name.
     */
    protected $_validatedTable;

    /**
     * @var string
     *  Unique/Primary key field name.
     */
    protected $_uniqueKey = NULL;

    /**
     * Parse field meta data out of provided PDO statement object into
     * provided RESTfmData object.
     *
     * @param RESTfmDataSimple $restfmData
     * @param PDOStatement $statement
     */
    protected function _parseMetaField(RESTfmDataSimple $restfmData, PDOStatement $statement) {
        $numColumns = $statement->columnCount();
        for ($i=0; $i < $numColumns; $i++) {
            $allFieldMeta = $statement->getColumnMeta($i);
            $fieldName = $allFieldMeta['name'];

            // Keep only required fields.
            $requiredFields = array('native_type', 'flags', 'len', 'precision');
            $fieldMeta = array();
            foreach($requiredFields as $requiredField) {
                if ($requiredField == 'flags') {
                    // Mysql:
                    if ($this->_uniqueKey === NULL) {
                        if (in_array('unique_key', $allFieldMeta['flags']) || in_array('primary_key', $allFieldMeta['flags'])) {
                            $this->_uniqueKey = $fieldName;
                        }
                    }
                    // Join flags field array into a string.
                    $fieldMeta[$requiredField] = join(', ', $allFieldMeta['flags']);
                } else {
                    $fieldMeta[$requiredField] = $allFieldMeta[$requiredField];
                }
            }

            $restfmData->pushFieldMeta($fieldName, $fieldMeta);
        }
    }

};
