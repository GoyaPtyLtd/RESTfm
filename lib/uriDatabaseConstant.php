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
require_once 'RESTfm/RESTfmQueryString.php';
require_once 'RESTfm/RESTfmDataSimple.php';

/**
 * RESTfm database element handler for Tonic.
 *
 * GET returns two "constant" records, one linking to "layout" and
 * one linking to "script". We do this to maintain the REST URI hierarchy.
 *
 * @uri /{database}
 */
class uriDatabaseConstant extends RESTfmResource {

    const URI = '/{database}';

    /**
     * Handle a GET request for this resource
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}
     *
     * @return Response
     */
    function get($request, $database) {
        $database = RESTfmUrl::decode($database);

        // Query available layouts. We don't use the results, simply validating
        // the database and credentials.
        $backend = BackendFactory::make($request, $database);
        $opsDatabase = $backend->makeOpsDatabase($database);
        $restfmDataLayouts = $opsDatabase->readLayouts();

        $queryString = new RESTfmQueryString(TRUE);

        $response = new RESTfmResponse($request);
        $format = $response->format;

        // Build static hrefs for navigation.
        $restfmData = new RESTfmDataSimple();
        $restfmData->pushDataRow( array('resource'    =>  'layout'), NULL,
            $request->baseUri.'/'.RESTfmUrl::encode($database).'/layout.'.$format.$queryString->build() );
        if (RESTfmConfig::getVar('settings', 'diagnostics') === TRUE) {
            $restfmData->pushDataRow( array('resource'    =>  'echo'), NULL,
                $request->baseUri.'/'.RESTfmUrl::encode($database).'/echo.'.$format.$queryString->build() );
        }
        $restfmData->pushDataRow( array('resource'    =>  'script'), NULL,
            $request->baseUri.'/'.RESTfmUrl::encode($database).'/script.'.$format.$queryString->build() );

        $response->setStatus(Response::OK);
        $response->setData($restfmData);
        return $response;
    }

};
