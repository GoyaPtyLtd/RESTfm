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
        $fmDataApi = $this->_backend->getFileMakerDataApi(); // @var FileMakerDataApi

        $params = array();

        // Handle unique-key-recordID OR literal recordID.
        $record = NULL;
        if (strpos($recordID, '=')) {
            list($searchField, $searchValue) = explode('=', $recordID, 2);
            $query = array( array( $searchField => $searchValue ) );
            $result = $fmDataApi->findRecords(
                            $this->_layout,
                            $query,
                            $params
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
            $result = $fmDataApi->getRecord($this->_layout, $recordID, $params);

            if ($result->isError()) {
                throw new FileMakerDataApiResponseException($result);
            }
        }

        $record = $result->getFirstRecord();

        // Ensure $fieldName exists in returned $record
        if (! isset($record['fieldData'][$fieldName])) {
            throw new \RESTfm\ResponseException(NULL, \RESTfm\ResponseException::NOTFOUND);
        }

        $fieldData = $record['fieldData'][$fieldName];

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
                $filename = '';
                $matches = array();
                if (preg_match('/\/([^\/\?]*)\?/', $fieldData, $matches)) {
                    $filename = $matches[1];
                }
                $containerData = $fmDataApi->getContainerData($fieldData);
                if (gettype($containerData) !== 'string') {
                    $containerData = "";
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

};
