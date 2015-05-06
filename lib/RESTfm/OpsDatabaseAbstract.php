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
 * OpsDatabaseAbstract
 *
 * Wraps all database-level operations to database backend(s). This includes
 * queries that do not operate at the record level, such as listing
 * databases, layouts, etc.
 *
 * All data I/O is encapsulated in a RESTfmData object.
 */
abstract class OpsDatabaseAbstract {

    /**
     * @var BackendAbstract
     *  Handle to backend object. Implementation should set this in
     *  constructor.
     */
    protected $_backend = NULL;

    /**
     * Construct a new Database-level Operation object.
     *
     * @param BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     */
    abstract public function __construct (BackendAbstract $backend, $database = NULL);

    /**
     * Read databases available via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta' sections.
     */
    abstract public function readDatabases ();

    /**
     * Read layouts available in $database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta' sections.
     */
    abstract public function readLayouts ();

    /**
     * Read scripts available in $database via backend.
     *
     * @throws RESTfmResponseException
     *  On backend error.
     *
     * @return RESTfmDataAbstract
     *  - 'data', 'meta' sections.
     */
    abstract public function readScripts ();

};
