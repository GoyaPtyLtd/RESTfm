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

require 'OpsDatabaseAbstract.php';
require 'OpsLayoutAbstract.php';
require 'OpsRecordAbstract.php';

/**
 * Defines interface for initialisation of database backend, and factory
 * methods for creating operations objects.
 */
abstract class BackendAbstract {

    /**
     * Instantiate backend.
     *
     * @param string $host
     *  Hostname/Hostspec for backend database.
     * @param string $username
     * @param string $password
     */
    abstract public function __construct ($host, $username, $password);

    /**
     * Instantiate and return the appropriate Database Operations object for
     * the specified database.
     *
     * @param string $database
     *
     * @return OpsDatabaseAbstract;
     */
    abstract public function makeOpsDatabase ($database = NULL);

    /**
     * Instantiate and return the appropriate Layout Operations object for
     * the specified database.
     *
     * @param string $database
     * @param string $layout
     *
     * @return OpsLayoutAbstract;
     */
    abstract public function makeOpsLayout ($database, $layout);

    /**
     * Instantiate and return the appropriate Record Operations object for
     * the specified database.
     *
     * @param string $database
     * @param string $layout
     *
     * @return OpsRecordAbstract
     */
    abstract public function makeOpsRecord ($database, $layout);

}
