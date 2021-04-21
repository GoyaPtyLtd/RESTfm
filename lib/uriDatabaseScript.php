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

/**
 * RESTfm script collection handler for Tonic
 *
 * @uri /{database}/script
 */
class uriDatabaseScript extends RESTfm\Resource {

    const URI = '/{database}/script';

    /**
     * Handle a GET request for this resource
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/script
     *
     * @return Response
     */
    function get($request, $database) {
        $database = RESTfm\Url::decode($database);

        $backend = RESTfm\BackendFactory::make($request, $database);
        $opsDatabase = $backend->makeOpsDatabase($database);
        $restfmMessage = $opsDatabase->readScripts();

        $response = new RESTfm\Response($request);

        $response->setStatus(RESTfm\Response::OK);
        $response->setMessage($restfmMessage);
        return $response;
    }

};
