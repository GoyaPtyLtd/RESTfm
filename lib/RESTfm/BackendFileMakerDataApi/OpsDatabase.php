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
class OpsDatabase extends \RESTfm\OpsDatabaseAbstract {

    /**
     * Construct a new Database-level Operation object.
     *
     * @param \RESTfm\BackendAbstract $backend
     *  Implementation must store $this->_backend if a reference is needed in
     *  other methods.
     * @param string $database
     */
    public function __construct (\RESTfm\BackendAbstract $backend, $database = NULL) {
        $this->_backend = $backend;
    }

    /**
     * Read databases available - Not applicable to this backend, as the
     * databases are hard coded under databaseFMDataAPIMap in the RESTfm config.
     *
     * @return \RESTfm\Message\Message
     *   Empty, no sections.
     */
    public function readDatabases () {
        return new \RESTfm\Message\Message;
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
        $message = new \RESTfm\Message\Message();
        $map = $this->_backend->getDbMap();
        if (isset($map['layouts'])) {
            foreach ($map['layouts'] as $layout) {
                $message->addRecord(new \RESTfm\Message\Record(
                        NULL,
                        NULL,
                        array('layout' => $layout)
                    ));
            }
        }
        return $message;
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
        return new \RESTfm\Message\Message();
    }

};
