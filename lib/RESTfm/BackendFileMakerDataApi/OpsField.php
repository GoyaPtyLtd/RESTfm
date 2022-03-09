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
     * @var \RESTfm\BackendFileMakerDataApi\Backend
     *  Handle to backend object.
     */
    protected $_backend = NULL;

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
            // request field metadata to verify our field is a container.
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
                        $response->setBody( $containerData,
                                            $fmDataApi->getContainerDataHeader('Content-Type'),
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
     *
     * @throws \RESTfm\ResponseException
     * @throws FileMakerDataApiResponseException
     *
     * @return \RESTfm\Message\Message
     */
    public function update ($recordID, $fieldName) {

        // We have to make sure the recordID exists before trying to update
        // a field.
        $existingResult = $this->_getRecord($recordID);

        // In case the recordID was a unique-key-recordID, we will use the
        // found one.
        $existingRecord = $existingResult->getFirstRecord();
        $recordID = $existingRecord['recordId'];

        $fmDataApi = $this->_backend->getFileMakerDataApi();

        // Commit new field data back to database.
        $result = $fmDataApi->editRecord(   $this->_layout,
                                            $recordID,
                                            array (
                                                $fieldName => 'somedata',
                                            )
                                        );

        if ($result->isError()) {
            throw new FileMakerDataApiResponseException($result);
        }
    }

    /**
     * Wraps FileMakerDataApi->getRecord() with handling for
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
