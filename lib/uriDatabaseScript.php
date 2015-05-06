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

require_once 'RESTfm/RESTfmResource.php';
require_once 'RESTfm/RESTfmResponse.php';

/**
 * RESTfm script collection handler for Tonic
 *
 * @uri /{database}/script
 */
class uriDatabaseScript extends RESTfmResource {

    const URI = '/{database}/script';

    /**
     * Handle a GET request for this resource
     *
     * @param Request $request
     * @param string $database
     *   From URI parsing: /{database}/script
     *
     * @return Response
     */
    function get(Request $request, $database) {
        $database = RESTfmUrl::decode($database);

        $backend = BackendFactory::make($request, $database);
        $opsDatabase = $backend->makeOpsDatabase($database);
        $restfmData = $opsDatabase->readScripts();

        $response = new RESTfmResponse($request);

        $response->setStatus(Response::OK);
        $response->setData($restfmData);
        return $response;
    }

};
