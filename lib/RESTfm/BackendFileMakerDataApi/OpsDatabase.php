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
 * FileMaker Data API implementation of OpsDatabaseAbstract.
 */
class OpsDatabase implements \RESTfm\OpsDatabaseAbstract {

    /**
     * Construct a new Database-level Operation object.
     *
     * @param \RESTfm\BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     */
    public function __construct (\RESTfm\BackendAbstract $backend, $database = NULL) {

    }

    /**
     * Read databases available via backend.
     *
     * @throws \RESTFm\ResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readDatabases () {

    }

    /**
     * Read layouts available in $database via backend.
     *
     * @throws \RESTFm\ResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readLayouts () {

    }

    /**
     * Read scripts available in $database via backend.
     *
     * @throws \RESTFm\ResponseException
     *  On backend error.
     *
     * @return \RESTfm\Message\Message
     */
    public function readScripts () {

    }

};
