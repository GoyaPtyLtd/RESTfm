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
 * FileMaker Data API implementation of OpsFieldAbstract.
 */
class OpsField extends \RESTfm\OpsFieldAbstract {

    /**
     * @var \RESTfm\BackendFileMakerDataApi\Backend|null
     *  Handle to backend object.
     */
    protected $_backend = NULL;

    /**
     * @var string
     *  Layout name.
     */
    protected $_layout = '';

    /**
     * Construct a new Field-level Operation object.
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
     * Read field specified by $recordID and $fieldName, and
     * populate $response directly.
     *
     * @param \RESTfm\FieldResponse $response
     * @param string $recordID
     * @param string $fieldName
     */
    public function read (\RESTfm\FieldResponse $response, $recordID, $fieldName) {

        $result = $this->_getRecord($recordID);

        $record = $result->getFirstRecord();

        // Ensure $fieldName exists in returned $record
        if (! isset($record['fieldData'][$fieldName])) {
            throw new \RESTfm\ResponseException(NULL, \RESTfm\ResponseException::NOTFOUND);
        }
        $fieldData = $record['fieldData'][$fieldName];

        $fmDataApi = $this->_backend->getFileMakerDataApi();

        if ($this->_containerEncoding !== self::CONTAINER_DEFAULT) {
            // Since some kind of container encoding is requested, we need to
            // check if this field is a container.
            $containerFields = $this->_getContainerFields();

            if (array_key_exists($fieldName, $containerFields)) {
                // Handle this as a container field and return

                $filename = $this->_containerFilename;
                if ($filename == NULL) {
                    // Extract filename from container URL (this is just
                    // random, but at least has an extension).
                    $matches = array();
                    if (preg_match('/\/([^\/\?]*)\?/', $fieldData, $matches)) {
                        $filename = $matches[1];
                    }
                }
                $containerData = $fmDataApi->getContainerData($fieldData);
                if (gettype($containerData) !== 'string') {
                    $containerData = '';
                }
                switch ($this->_containerEncoding) {
                    case self::CONTAINER_BASE64:
                        $response->setBody( $filename . ';' . base64_encode($containerData),
                                            'text/plain'
                                          );
                        break;
                    case self::CONTAINER_RAW:
                        if ($this->_containerMimeType !== NULL) {
                            $mimeType = $this->_containerMimeType;
                        } else {
                            $mimeType = $fmDataApi->getContainerDataHeader('Content-Type');

                        }
                        $response->setBody( $containerData,
                                            $mimeType,
                                            $fmDataApi->getContainerDataHeader('Content-Length')
                                          );
                        if (!empty($filename)) {
                            $response->addHeader('Content-Disposition', 
                                        'filename="' . $filename . '"');
                        }
                        break;
                    case self::CONTAINER_DEFAULT:
                    default:
                        $response->setBody( $fieldData, 'text/plain');
                }

                return;
            }
        }

        // Handle this as an ordinary (non-container) field.
        $response->setBody( $fieldData, 'text/plain');
    }

    /**
     * Update field specified by $recordID and $fieldName.
     *
     * @param string $recordID
     * @param string $fieldName
     * @param string $data
     *
     * @throws \RESTfm\ResponseException
     * @throws FileMakerDataApiResponseException
     */
    public function update ($recordID, $fieldName, $data) {

        // We have to make sure the recordID exists before trying to update
        // a field.
        $existingResult = $this->_getRecord($recordID);

        // In case the recordID was a unique-key-recordID, we will use the
        // found one.
        $existingRecord = $existingResult->getFirstRecord();
        $recordID = $existingRecord['recordId'];

        $fmDataApi = $this->_backend->getFileMakerDataApi();

        if ($this->_containerEncoding !== self::CONTAINER_DEFAULT) {
            // Since some kind of container encoding is requested, we need to
            // check if this field is a container.
            $containerFields = $this->_getContainerFields();

            if (array_key_exists($fieldName, $containerFields)) {
            // Handle this as a container field and return

                switch ($this->_containerEncoding) {
                    case self::CONTAINER_BASE64:
                        $data = base64_decode($data);
                        break;
                    case self::CONTAINER_RAW:
                        break;
                }
                $result = $fmDataApi->uploadToContainerField(
                                                $this->_layout,
                                                $recordID,
                                                $fieldName,
                                                $data,
                                                $this->_containerMimeType,
                                                $this->_containerFilename);
            }

            if ($result->isError()) {
                throw new FileMakerDataApiResponseException($result);
            }

            return;
        }

        // Commit new field data back to database.
        $result = $fmDataApi->editRecord(   $this->_layout,
                                            $recordID,
                                            array (
                                                $fieldName => $data,
                                            )
                                        );

        if ($result->isError()) {
            throw new FileMakerDataApiResponseException($result);
        }
    }

    /**
     * Fetch field metadata and return assoc array of container fields.
     *
     * @return array
     *  Associative array of container fields, in the format:
     *      ('fieldName' => 1, ...)
     */
    private function _getContainerFields () {
        $fmDataApi = $this->_backend->getFileMakerDataApi();

        $metaDataResult = $fmDataApi->layoutMetadata($this->_layout);
        $fieldMetaData = $metaDataResult->getFieldMetaData();
        $containerFields = array();
        foreach ($fieldMetaData as $metaData) {
            if (isset($metaData['name']) &&
                    isset($metaData['result']) &&
                    $metaData['result'] == 'container') {
                $containerFields[$metaData['name']] = 1;
            }
        }

        return $containerFields;
    }

    /**
     * Wraps FileMakerDataApi::getRecord() with handling for
     * unique-key-recordID, and exceptions for not-found cases.
     *
     * @param string $recordID
     *
     * @throws \RESTfm\ResponseException
     *  cURL and JSON errors.
     * @throws FileMakerDataApiResponseException
     *  Error from FileMaker Data API Server.
     *
     * @return FileMakerDataApiResult
     */
    private function _getRecord($recordID) {
        $fmDataApi = $this->_backend->getFileMakerDataApi();

        // Handle unique-key-recordID OR literal recordID.
        if (strpos($recordID, '=')) {
            list($searchField, $searchValue) = explode('=', $recordID, 2);
            $query = array( array( $searchField => $searchValue ) );
            $result = $fmDataApi->findRecords(
                            $this->_layout,
                            $query
                        );

            if ($result->isError()) {
                if ($result->getCode() == 401) {
                    // "No records match the request"
                    // This is a special case where we actually want to return
                    // 404. ONLY because we are a unique-key-recordID.
                    throw new \RESTfm\ResponseException(NULL, \RESTfm\ResponseException::NOTFOUND);
                } else {
                    throw new FileMakerDataApiResponseException($result);
                }
            }

            if ($result->getFetchCount() > 1) {
                // We have to abort if the search query recordID is not unique.
                throw new \RESTfm\ResponseException($result->getFetchCount() .
                        ' conflicting records found', \RESTfm\ResponseException::CONFLICT);
            }

        } else {
            $result = $fmDataApi->getRecord($this->_layout, $recordID);

            if ($result->isError()) {
                throw new FileMakerDataApiResponseException($result);
            }
        }

        return $result;
    }

};
