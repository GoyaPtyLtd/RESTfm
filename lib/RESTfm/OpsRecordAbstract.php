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

namespace RESTfm;

/**
 * OpsRecordAbstract
 *
 * Wraps all record-level operations to database backend(s). All data I/O is
 * encapsulated in a \RESTfm\Message\Message object, including result codes for the
 * operation(s).
 */
abstract class OpsRecordAbstract {

    /**
     * @var \RESTfm\BackendAbstract
     *  Handle to backend object. Implementation should set this in
     *  constructor.
     */
    protected $_backend = NULL;

    // --- Abstract methods --- //

    /**
     * Construct a new Record-level Operation object.
     *
     * @param \RESTfm\BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     * @param string $layout
     */
    abstract public function __construct (\RESTfm\BackendAbstract $backend, $database, $layout);

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
    abstract protected function _createRecord (\RESTfm\Message\Message $restfmMessage, \RESTfm\Message\Record $requestRecord, $index);

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
    abstract protected function _readRecord (\RESTfm\Message\Message $restfmMessage, \RESTfm\Message\Record $requestRecord);

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
    abstract protected function _updateRecord (\RESTfm\Message\Message $restfmMessage, \RESTfm\Message\Record $requestRecord, $index);

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
    abstract protected function _deleteRecord (\RESTfm\Message\Message $restfmMessage, \RESTfm\Message\Record $requestRecord);

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
    abstract public function callScript ($scriptName, $scriptParameter = NULL);

    // -- Public methods --

    /**
     * Create record from the provided \RESTfm\Message\Message object.
     * Convenience method wraps bulk operation method.
     *
     * @param \RESTfm\Message\Message $requestMessage
     *
     * @throws \RESTfm\ResponseException
     *  On invalid $requestMessage.
     *
     * @return \RESTfm\Message\Message
     *  - 'meta' section.
     *  - 'multistatus' section only if an error occurred.
     */
    public function createSingle (\RESTfm\Message\Message $requestMessage) {
        $this->_setSingle(TRUE);
        return $this->createBulk($requestMessage);
    }

    /**
     * Create record(s) from the provided \RESTfm\Message\Message object.
     *
     * @param \RESTfm\Message\Message $requestMessage
     *  'data' section required with row(s) containing record data.
     *
     * @throws \RESTfm\ResponseException
     *  On invalid $requestMessage.
     *
     * @return \RESTfm\Message\Message
     *  - 'meta' section.
     *  - 'multistatus' section only if an error occurred.
     */
    public function createBulk (\RESTfm\Message\Message $requestMessage) {
        if ($requestMessage->getRecordCount() < 1) {
            throw new ResponseException('No records found in request.', ResponseException::BADREQUEST);
        }

        $result = new \RESTfm\Message\Message();

        // Trigger preOpScript on the first element.
        if ($this->_preOpScript !== NULL) {
            $this->_preOpScriptTrigger = TRUE;
        }

        // Trigger postOpScript on the last element.
        if ($this->_postOpScript != NULL) {
            $postOpTriggerCount = $requestMessage->getRecordCount();
        } else {
            $postOpTriggerCount = -1;
        }

        $requestRecord = NULL;  // @var \RESTfm\Message\Record
        $index = 0;
        foreach($requestMessage->getRecords() as $requestRecord) {
            $index++;
            if ($index == $postOpTriggerCount) {
                $this->_postOpScriptTrigger = TRUE;
            }
            $this->_createRecord($result, $requestRecord, $index);
        }

        return $result;
    }

    /**
     * Read record by the provided record ID.
     * Convenience method wraps bulk operation method.
     *
     * @param \RESTfm\Message\Record $requestRecord
     *  Must have recordID set.
     *
     * @throws \RESTfm\ResponseException
     *  On invalid $requestMessage.
     *
     * @return \RESTfm\Message\Message
     *  - 'data', 'meta', 'metaField' sections.
     *  - 'multistatus' section only if an error occurred.
     */
    public function readSingle (\RESTfm\Message\Record $requestRecord) {
        $this->_setSingle(TRUE);
        $requestRestfmMessage = new \RESTfm\Message\Message();
        $requestRestfmMessage->addRecord($requestRecord);
        return $this->readBulk($requestRestfmMessage);
    }

    /**
     * Read record(s) from the provided \RESTfm\Message\Message object.
     *
     * @param \RESTfm\Message\Message $requestMessage
     *  'meta' section required with row(s) containing a 'recordID' field.
     *
     * @throws \RESTfm\ResponseException
     *  On invalid $requestMessage.
     *
     * @return \RESTfm\Message\Message
     *  - 'data', 'meta', 'metaField' sections.
     *  - 'multistatus' section only if an error occurred.
     */
    public function readBulk (\RESTfm\Message\Message $requestMessage) {
        if ($requestMessage->getRecordCount() < 1) {
            throw new ResponseException('No records found in request.', ResponseException::BADREQUEST);
        }

        $result = new \RESTfm\Message\Message();

        // Trigger preOpScript on the first element.
        if ($this->_preOpScript !== NULL) {
            $this->_preOpScriptTrigger = TRUE;
        }

        // Trigger postOpScript on the last element.
        if ($this->_postOpScript != NULL) {
            $postOpTriggerCount = $requestMessage->getRecordCount();
        } else {
            $postOpTriggerCount = -1;
        }

        $requestRecord = NULL;  // @var \RESTfm\Message\Record
        $index = 0;
        foreach($requestMessage->getRecords() as $requestRecord) {
            $index++;
            if ($index == $postOpTriggerCount) {
                $this->_postOpScriptTrigger = TRUE;
            }
            $this->_readRecord($result, $requestRecord);
        }

        return $result;
    }

    /**
     * Update record from the provided \RESTfm\Message\Record object.
     * Convenience method wraps bulk operation method.
     *
     * @param \RESTfm\Message\Message $requestMessage
     *  Must contain row data and recordID
     *
     * @throws \RESTfm\ResponseException
     *  On invalid $requestRecord.
     *
     * @return \RESTfm\Message\Message
     *  - 'multistatus' section only if an error occurred.
     */
    public function updateSingle (\RESTfm\Message\Message $requestMessage) {
        $this->_setSingle(TRUE);
        return $this->updateBulk($requestMessage);
    }

    /**
     * Update record(s) from the provided \RESTfm\Message\Message object.
     *
     * @param \RESTfm\Message\Message $requestMessage
     *  'data' section required with row(s) containing record data.
     *  'meta' section required with row(s) containing a 'recordID' field.
     *
     * @throws \RESTfm\ResponseException
     *  On invalid $requestMessage.
     *
     * @return \RESTfm\Message\Message
     *  - 'multistatus' section only if an error occurred.
     */
    public function updateBulk (\RESTfm\Message\Message $requestMessage) {
        if ($requestMessage->getRecordCount() < 1) {
            throw new ResponseException('No records found in request.', ResponseException::BADREQUEST);
        }

        $result = new \RESTfm\Message\Message();

        // Trigger preOpScript on the first element.
        if ($this->_preOpScript !== NULL) {
            $this->_preOpScriptTrigger = TRUE;
        }

        // Trigger postOpScript on the last element.
        if ($this->_postOpScript != NULL) {
            $postOpTriggerCount = $requestMessage->getRecordCount();
        } else {
            $postOpTriggerCount = -1;
        }

        $requestRecord = NULL;  // @var \RESTfm\Message\Record
        $i = 0;
        foreach($requestMessage->getRecords() as $requestRecord) {
            $i++;
            if ($i == $postOpTriggerCount) {
                $this->_postOpScriptTrigger = TRUE;
            }
            $this->_updateRecord($result, $requestRecord, $i);
        }

        return $result;
    }

    /**
     * Delete single record.
     * Convenience method wraps bulk operation method.
     *
     * @param \RESTfm\Message\Record $requestRecord
     *  Must contain recordID.
     *
     * @throws \RESTfm\ResponseException
     *  On invalid $requestMessage.
     *
     * @return \RESTfm\Message\Message
     *  - 'multistatus' section only if an error occurred.
     */
    public function deleteSingle (\RESTfm\Message\Record $requestRecord) {
        $this->_setSingle(TRUE);
        $requestRestfmMessage = new \RESTfm\Message\Message();
        $requestRestfmMessage->addRecord($requestRecord);
        return $this->deleteBulk($requestRestfmMessage);
    }

    /**
     * Delete record(s) from the provided \RESTfm\Message\Message object.
     *
     * @param \RESTfm\Message\Message $requestMessage
     *  'meta' section required with row(s) containing a 'recordID' field.
     *
     * @throws \RESTfm\ResponseException
     *  On invalid $requestMessage.
     *
     * @return \RESTfm\Message\Message
     *  - 'multistatus' section only if an error occurred.
     */
    public function deleteBulk (\RESTfm\Message\Message $requestMessage) {
        if ($requestMessage->getRecordCount() < 1) {
            throw new ResponseException('No records found in request.', ResponseException::BADREQUEST);
        }

        $result = new \RESTfm\Message\Message();

        // Trigger preOpScript on the first element.
        if ($this->_preOpScript !== NULL) {
            $this->_preOpScriptTrigger = TRUE;
        }

        // Trigger postOpScript on the last element.
        if ($this->_postOpScript != NULL) {
            $postOpTriggerCount = $requestMessage->getRecordCount();
        } else {
            $postOpTriggerCount = -1;
        }

        $requestRecord = NULL;  // @var \RESTfm\Message\Record
        $i = 0;
        foreach($requestMessage->getRecords() as $requestRecord) {
            $i++;
            if ($i == $postOpTriggerCount) {
                $this->_postOpScriptTrigger = TRUE;
            }
            $this->_deleteRecord($result, $requestRecord);
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
    public function setContainerEncoding ($encoding = self::CONTAINER_DEFAULT) {
        $this->_containerEncoding = $encoding;
    }

    /**
     * Suppress 'data' section in \RESTfm\Message\Message result. Also suppresses
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
     * is done by throwing a ResponseException during CRUD operation.
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
     *  Flag to suppress 'data' section in \RESTfm\Message\Message.
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
