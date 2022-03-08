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
 * OpsFieldAbstract
 *
 * Wraps all field-level operations to database backend(s).
 */
abstract class OpsFieldAbstract {

    /**
     * @var \RESTfm\BackendAbstract
     *  Handle to backend object. Implementation should set this in
     *  constructor.
     */
    protected $_backend = NULL;

    // --- Abstract methods --- //

    /**
     * Construct a new Field-level Operation object.
     *
     * @param \RESTfm\BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     * @param string $layout
     */
    abstract public function __construct (\RESTfm\BackendAbstract $backend, $database, $layout);

    /**
     * Read field specified by $recordID and $fieldName, and
     * populate $response directly.
     *
     * @param \RESTfm\FieldResponse $response
     * @param string $recordID
     * @param string $fieldName
     */
    abstract public function read (\RESTfm\FieldResponse $response, $recordID, $fieldName);

    /**
     * Update field specified by $recordID and $fieldName.
     *
     * @param string $recordID
     * @param string $fieldName
     *
     * @throws \RESTfm\ResponseException
     *
     * @return \RESTfm\Message\Message
     */
    abstract public function update ($recordID, $fieldName);

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

    // -- Protected properties --

    /**
     * @var integer
     *  Requested container encoding format.
     */
    protected $_containerEncoding = self::CONTAINER_DEFAULT;

};
