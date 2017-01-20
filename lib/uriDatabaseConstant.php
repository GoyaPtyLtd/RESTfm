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

        // Create virtual records with static hrefs purely for navigation.
        $restfmMessage = new RESTfmMessage();
        $restfmMessage->addRecord(new RESTfmMessageRecord(
            NULL,
            $request->baseUri.'/'.RESTfmUrl::encode($database).
                    '/layout.'.$format.$queryString->build(),
            array('resource' => 'layout')
        ));
        if (RESTfmConfig::getVar('settings', 'diagnostics') === TRUE) {
            $restfmMessage->addRecord(new RESTfmMessageRecord(
                NULL,
                $request->baseUri.'/'.RESTfmUrl::encode($database).
                        '/echo.'.$format.$queryString->build(),
                array('resource' => 'echo')
            ));
        }
        $restfmMessage->addRecord(new RESTfmMessageRecord(
            NULL,
            $request->baseUri.'/'.RESTfmUrl::encode($database).
                    '/script.'.$format.$queryString->build(),
            array('resource' => 'script')
        ));

        $response->setStatus(Response::OK);
        $response->setRestfmMessage($restfmMessage);
        return $response;
    }

};
