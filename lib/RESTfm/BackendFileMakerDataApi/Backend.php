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
 * FileMaker Data API implementation of BackendAbstract.
 */
class BackendAbstract implements \RESTfm\BackendAbstract{

    /**
     * Instantiate backend.
     *
     * @param string $host
     *  Hostname/Hostspec for backend database.
     * @param string $username
     * @param string $password
     */
    public function __construct ($host, $username, $password) {

    }

    /**
     * Instantiate and return the appropriate Database Operations object for
     * the specified database.
     *
     * @param string $database
     *
     * @return OpsDatabaseAbstract;
     */
    public function makeOpsDatabase ($database = NULL) {

    }

    /**
     * Instantiate and return the appropriate Layout Operations object for
     * the specified database.
     *
     * @param string $database
     * @param string $layout
     *
     * @return OpsLayoutAbstract;
     */
    public function makeOpsLayout ($database, $layout) {

    }

    /**
     * Instantiate and return the appropriate Record Operations object for
     * the specified database.
     *
     * @param string $database
     * @param string $layout
     *
     * @return OpsRecordAbstract
     */
    public function makeOpsRecord ($database, $layout) {
       
    }

}
