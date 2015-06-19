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

require_once 'BackendAbstract.php';
require_once 'RESTfmResponseException.php';
require_once 'RESTfmDataAbstract.php';

/**
 * OpsRecordAbstract
 *
 * Wraps all record-level operations to database backend(s). All data I/O is
 * encapsulated in a RESTfmData object, including result codes for the
 * operation(s).
 */
abstract class OpsRecordAbstract {

    /**
     * @var BackendAbstract
     *  Handle to backend object. Implementation should set this in
     *  constructor.
     */
    protected $_backend = NULL;

    // --- Abstract methods --- //

    /**
     * Construct a new Record-level Operation object.
     *
     * @param BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     * @param string $layout
     */
    abstract public function __construct (BackendAbstract $backend, $database, $layout);

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
    abstract protected function _createRecord (RESTfmDataSimple $restfmData, $index, $row);

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
    abstract protected function _readRecord (RESTfmDataSimple $restfmData, $recordID);

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
    abstract protected function _updateRecord (RESTfmDataSimple $restfmData, $recordID, $index, $row);

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
    abstract protected function _deleteRecord (RESTfmDataSimple $restfmData, $recordID);

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
    abstract public function callScript ($scriptName, $scriptParameter = NULL);

    // -- Public methods --

    /**
     * Create record from the provided RESTfmDataAbstract object.
     * Convenience method wraps bulk operation method.
     *
     * @param RESTfmDataAbstract $queryData
     *  'data' section required with row containing record data.
     *
     * @throws RESTfmResponseException
     *  On invalid $queryData.
     *
     * @return RESTfmDataAbstract
     *  - 'meta' section.
     *  - 'multistatus' section only if an error occurred.
     */
    public function createSingle (RESTfmDataAbstract $queryData) {
        $this->_setSingle(TRUE);
        return $this->createBulk($queryData);
    }

    /**
     * Create record(s) from the provided RESTfmDataAbstract object.
     *
     * @param RESTfmDataAbstract $queryData
     *  'data' section required with row(s) containing record data.
     *
     * @throws RESTfmResponseException
     *  On invalid $queryData.
     *
     * @return RESTfmDataAbstract
     *  - 'meta' section.
     *  - 'multistatus' section only if an error occurred.
     */
    public function createBulk (RESTfmDataAbstract $queryData) {
        if (! $queryData->sectionExists('data')) {
            throw new RESTfmResponseException('No data section found.', RESTfmResponseException::BADREQUEST);
        }

        $result = new RESTfmDataSimple();

        // Trigger preOpScript on the first element.
        if ($this->_preOpScript !== NULL) {
            $this->_preOpScriptTrigger = TRUE;
        }

        // Trigger postOpScript on the last element.
        if ($this->_postOpScript != NULL) {
            $postOpTriggerCount = $queryData->getSectionCount('data');
        } else {
            $postOpTriggerCount = -1;
        }

        $queryData->setIteratorSection('data');
        $i = 0;
        foreach($queryData as $index => $row) {
            $i++;
            if ($i == $postOpTriggerCount) {
                $this->_postOpScriptTrigger = TRUE;
            }
            $this->_createRecord($result, $index, $row);
        }

        return $result;
    }

    /**
     * Read record by the provided record ID.
     * Convenience method wraps bulk operation method.
     *
     * @param string $recordID
     *  String containing record ID to retrieve.
     *
     * @throws RESTfmResponseException
     *  On invalid $queryData.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta', 'metaField' sections.
     *  - 'multistatus' section only if an error occurred.
     */
    public function readSingle ($recordID) {
        $this->_setSingle(TRUE);

        $requestRestfmData = new RESTfmData();
        $requestRestfmData->setSectionData('meta', $recordID, array('recordID' => $recordID));

        return $this->readBulk($requestRestfmData);
    }

    /**
     * Read record(s) from the provided RESTfmDataAbstrace object.
     *
     * @param RESTfmDataAbstract $queryData
     *  'meta' section required with row(s) containing a 'recordID' field.
     *
     * @throws RESTfmResponseException
     *  On invalid $queryData.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta', 'metaField' sections.
     *  - 'multistatus' section only if an error occurred.
     */
    public function readBulk (RESTfmDataAbstract $queryData) {
        if (! $queryData->sectionExists('meta')) {
            throw new RESTfmResponseException('No meta section found.', RESTfmResponseException::BADREQUEST);
        }

        $result = new RESTfmDataSimple();

        $queryData->setIteratorSection('meta');
        foreach($queryData as $row) {
            if (isset($row['recordID'])) {
                $this->_readRecord($result, $row['recordID']);
            }
        }

        return $result;
    }

    /**
     * Update record from the provided RESTfmDataAbstract object.
     * Convenience method wraps bulk operation method.
     *
     * @param RESTfmDataAbstract $queryData
     *  'data' section required with row containing record data.
     * @param string $recordID
     *  String containing record ID to update.
     *
     * @throws RESTfmResponseException
     *  On invalid $queryData.
     *
     * @return RESTfmDataAbstract
     *  - 'multistatus' section only if an error occurred.
     */
    public function updateSingle (RESTfmDataAbstract $queryData, $recordID) {
        $this->_setSingle(TRUE);

        // Inject recordID into meta section (we are a single operation, so just
        // one record).
        $queryData->setSectionData2nd('meta', 0, 'recordID', $recordID);

        return $this->updateBulk($queryData);
    }

    /**
     * Update record(s) from the provided RESTfmDataAbstract object.
     *
     * @param RESTfmDataAbstract $queryData
     *  'data' section required with row(s) containing record data.
     *  'meta' section required with row(s) containing a 'recordID' field.
     *
     * @throws RESTfmResponseException
     *  On invalid $queryData.
     *
     * @return RESTfmDataAbstract
     *  - 'multistatus' section only if an error occurred.
     */
    public function updateBulk (RESTfmDataAbstract $queryData) {
        if (! $queryData->sectionExists('meta') ||
                ! $queryData->sectionExists('data')) {
            throw new RESTfmResponseException('No data or no meta section found.', RESTfmResponseException::BADREQUEST);
        }

        $result = new RESTfmDataSimple();

        // Trigger preOpScript on the first element.
        if ($this->_preOpScript !== NULL) {
            $this->_preOpScriptTrigger = TRUE;
        }

        // Trigger postOpScript on the last element.
        if ($this->_postOpScript != NULL) {
            $postOpTriggerCount = $queryData->getSectionCount('data');
        } else {
            $postOpTriggerCount = -1;
        }

        $queryData->setIteratorSection('meta');
        $i = 0;
        foreach($queryData as $index => $row) {
            $i++;
            if ($i == $postOpTriggerCount) {
                $this->_postOpScriptTrigger = TRUE;
            }
            if (isset($row['recordID'])) {
                $this->_updateRecord(
                            $result,
                            $row['recordID'],
                            $index,
                            $queryData->getSectionData('data', $index)
                        );
            }
        }

        return $result;
    }

    /**
     * Delete single record.
     * Convenience method wraps bulk operation method.
     *
     * @param RESTfmDataAbstract $queryData
     *  Original RESTfmData from request.
     * @param string $recordID
     *  String containing record ID to delete.
     *
     * @throws RESTfmResponseException
     *  On invalid $queryData.
     *
     * @return RESTfmDataAbstract
     *  - 'multistatus' section only if an error occurred.
     */
    public function deleteSingle (RESTfmDataAbstract $queryData, $recordID) {
        $this->_setSingle(TRUE);

        // Inject recordID into meta section (we are a single operation, so just
        // one record).
        $queryData->setSectionData2nd('meta', 0, 'recordID', $recordID);

        return $this->deleteBulk($queryData);
    }

    /**
     * Delete record(s) from the provided RESTfmDataAbstract object.
     *
     * @param RESTfmDataAbstract $queryData
     *  'meta' section required with row(s) containing a 'recordID' field.
     *
     * @throws RESTfmResponseException
     *  On invalid $queryData.
     *
     * @return RESTfmDataAbstract
     *  - 'multistatus' section only if an error occurred.
     */
    public function deleteBulk (RESTfmDataAbstract $queryData) {
        if (! $queryData->sectionExists('meta')) {
            throw new RESTfmResponseException('No meta section found.', RESTfmResponseException::BADREQUEST);
        }

        $result = new RESTfmDataSimple();

        // Trigger preOpScript on the first element.
        if ($this->_preOpScript !== NULL) {
            $this->_preOpScriptTrigger = TRUE;
        }

        // Trigger postOpScript on the last element.
        if ($this->_postOpScript != NULL) {
            $postOpTriggerCount = $queryData->getSectionCount('data');
        } else {
            $postOpTriggerCount = -1;
        }

        $queryData->setIteratorSection('meta');
        $i = 0;
        foreach($queryData as $index => $row) {
            $i++;
            if ($i == $postOpTriggerCount) {
                $this->_postOpScriptTrigger = TRUE;
            }
            if (isset($row['recordID'])) {
                $this->_deleteRecord($result, $row['recordID']);
            }
        }

        return $result;
    }

    /**
     * Allowed container encoding formats.
     */
    const   CONTAINER_DEFAULT   = 0,
            CONTAINER_BASE64    = 1,
            CONTAINER_RAW       = 2;

    /**
     * Encode container data rather than returning the URL.
     *
     * @param integer $encoding
     *  CONTAINER_DEFAULT: FileMaker container URL.
     *  CONTAINER_BASE64: [<filename>;]<base64 encoding>
     *  CONTAINER_RAW: No RESTfm formatting, RAW data for single field returned.
     */
    public function setContainerEncoding ($encoding = CONTAINER_DEFAULT) {
        $this->_containerEncoding = $encoding;
    }

    /**
     * Suppress 'data' section in RESTfmData result. Also suppresses
     * 'metaField' section.
     *
     * By default FileMaker will return the record data from a create operation,
     * which is passed back by default. This flag will suppress that data.
     *
     * May be used by other OpsRecord methods in the future.
     */
    public function setSuppressData ($suppressData = TRUE) {
        $this->_suppressData = $suppressData;
    }

    /**
     * Append submitted record field data to existing data instead of
     * overwriting.
     *
     * @param boolean $updateAppend
     *  Set the append flag for update().
     */
    public function setUpdateAppend ($updateAppend = TRUE) {
        $this->_updateAppend = $updateAppend;
    }

    /**
     * If an update is requested on a non-existent recordID, create the
     * record instead.
     *
     * @param boolean $updateElseCreate
     *  Set the updateElseCreate flag for update().
     */
    public function setUpdateElseCreate ($updateElseCreate = TRUE) {
        $this->_updateElseCreate = $updateElseCreate;
    }

    /**
     * Set the script to be executed before performing an operation.
     *
     * @param string $scriptName
     *  A NULL value will disable script calling.
     * @param string $parameter
     *  Options parameter to $scriptName. Default: NULL
     */
    public function setPreOpScript ($scriptName, $parameter = NULL) {
        $this->_preOpScript = $scriptName;
        $this->_preOpScriptParameter = $parameter;
    }

    /**
     * Set the script to be executed after performing an operation.
     *
     * @param string $scriptName
     *  A NULL value will disable script calling.
     * @param string $parameter
     *  Options parameter to $scriptName. Default: NULL
     */
    public function setPostOpScript ($scriptName, $parameter = NULL) {
        $this->_postOpScript = $scriptName;
        $this->_postOpScriptParameter = $parameter;
    }

    // -- Protected methods --

    /**
     * Single operation (as opposed to bulk operation) requests should not have
     * a 'multistatus' section. The operation's status code should be in the
     * 'info' section, and should influence the HTTP response status code. This
     * is done by throwing a RESTfmResponseException during CRUD operation.
     *
     * This is how RESTfm functioned prior to the implementation of a bulk
     * operations interface (and backend abstraction), which is also utilised
     * by single operation requests.
     *
     * Single operation status codes need to map to http status codes as
     * best possible to provide a coherent RESTful interface. Where there
     * is no logical mapping, a generic 500 status with "<backend> error"
     * reason, and then backend specific status and reason included in
     * the 'info' section. e.g.:
     * X-RESTfm-Status          500
     * X-RESTfm-Reason          FileMaker Error
     * X-RESTfm-FM-Status       802
     * X-RESTfm-FM-Reason       Unable to open file
     *
     * @param boolean $isSingle
     *  Set TRUE if this is to be a single operation, not a bulk operation.
     */
    protected function _setSingle ($isSingle = TRUE) {
        $this->_isSingle = $isSingle;
    }

    // -- Protected properties --

    /**
     * @var boolean
     *  Flag this operation as a single (non-bulk) type.
     */
    protected $_isSingle = FALSE;

    /**
     * @var integer
     *  Requested container encoding format.
     */
    protected $_containerEncoding = self::CONTAINER_DEFAULT;

    /**
     * @var boolean
     *  Flag to suppress 'data' section in RESTfmData.
     */
    protected $_suppressData = FALSE;

    /**
     * @var boolean
     *  append flag for update().
     */
    protected $_updateAppend = FALSE;

    /**
     * @var boolean
     *  elseCreate flag for update().
     */
    protected $_updateElseCreate = FALSE;

    /**
     * @var array $_findCriteria
     *  Array of fieldName => testValue pairs, where testValue is
     *  in the FileMaker 'find' format:
     *  http://www.filemaker.com/help/html/find_sort.5.4.html
     */
    protected $_findCriteria = array();

    /**
     * @var array $_preOpScript
     */
    protected $_preOpScript = NULL;

    /**
     * @var array $_preOpScriptParameter
     */
    protected $_preOpScriptParameter = NULL;

    /**
     * @var boolean $_preOpScriptTrigger
     * preOpScript trigger flag.
     */
    protected $_preOpScriptTrigger = FALSE;

    /**
     * @var array $_postOpScript
     */
    protected $_postOpScript = NULL;

    /**
     * @var array $_postOpScriptParameter
     */
    protected $_postOpScriptParameter = NULL;

    /**
     * @var boolean $_postOpScriptTrigger
     * postOpScript trigger flag.
     */
    protected $_postOpScriptTrigger = FALSE;

};
