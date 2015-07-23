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
 * OpsLayoutAbstract
 *
 * Wraps all layout-level operations to database backend(s).
 *
 * All data I/O is encapsulated in a RESTfmData object.
 */
abstract class OpsLayoutAbstract {

    /**
     * @var BackendAbstract
     *  Handle to backend object. Implementation should set this in
     *  constructor.
     */
    protected $_backend = NULL;

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
     * Read records in layout in database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta', 'metaField' sections.
     */
    abstract public function read ();

    /**
     * Read field metadata in layout in database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'metaField' section.
     */
    abstract public function readMetaField ();

    // -- Implemented Methods --

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
     * Set limits for number of records returned by read().
     *
     * @param integer $offset
     *  Offset to start returning records. Default 0.
     *  May be set to -1 to signify 'end'. Where $offset will be calculated as
     *  totalRecords minus $count.
     * @param integer $count
     *  Number of records to return from $offset onwards. Default 24.
     *  May be set to 0 to signify 'all'.
     */
    public function setLimit ($offset = 0, $count = 24) {
        $this->_readOffset = $offset;
        $this->_readCount = $count;
    }

    /**
     * Apply a fieldName/parameter criterion to the records returned by read().
     * Parameters are in the FileMaker 'find' format:
     * http://www.filemaker.com/help/html/find_sort.5.4.html
     *
     * Criterion are ANDed.
     *
     * @param string $fieldName
     * @param string $testValue
     */
    public function addFindCriterion ($fieldName, $testValue) {
        $this->_findCriteria[$fieldName] = $testValue;
    }

    /**
     * Clear all criteria.
     */
    public function clearCriteria () {
        $this->_findCriteria = array();
    }

    /**
     * Set SQL-like query string.
     *
     * @param string $sql
     */
    public function setSQLquery ($sql) {
        $this->_SQLquery = $sql;
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

    // -- Protected --

    /**
     * @var integer
     *  Requested container encoding format.
     */
    protected $_containerEncoding = self::CONTAINER_DEFAULT;

    /**
     * @var integer $_readOffset
     *  Offset for records returned by read().
     */
    protected $_readOffset = 0;

    /**
     * @var integer $_readCount
     *  Number of records returned by read().
     */
    protected $_readCount = 24;

    /**
     * @var array $_findCriteria
     *  Array of fieldName => testValue pairs, where testValue is
     *  in the FileMaker 'find' format:
     *  http://www.filemaker.com/help/html/find_sort.5.4.html
     */
    protected $_findCriteria = array();

    /**
     * @var string $_SQLquery
     *  SQL-like query string, that may include SELECT, WHERE, ORDER BY,
     *  OFFSET and LIMIT.
     */
    protected $_SQLquery = NULL;

    /**
     * @var array $_preOpScript
     */
    protected $_preOpScript = NULL;

    /**
     * @var array $_preOpScriptParameter
     */
    protected $_preOpScriptParameter = NULL;

    /**
     * @var array $_postOpScript
     */
    protected $_postOpScript = NULL;

    /**
     * @var array $_postOpScriptParameter
     */
    protected $_postOpScriptParameter = NULL;

};
