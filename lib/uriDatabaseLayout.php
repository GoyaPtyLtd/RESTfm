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
 * RESTfm layout collection handler for Tonic
 *
 * @uri /{database}/layout
 */
class uriDatabaseLayout extends RESTfmResource {

    const URI = '/{database}/layout';

    /**
     * Handle a GET request for this resource
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/layout
     *
     * @return Response
     */
    function get($request, $database) {
        $database = RESTfmUrl::decode($database);

        $backend = BackendFactory::make($request, $database);
        $opsDatabase = $backend->makeOpsDatabase($database);
        $restfmMessage = $opsDatabase->readLayouts();

        $queryString = new QueryString(TRUE);

        $response = new RESTfm\Response($request);
        $format = $response->format;

        // Iterate records and set navigation hrefs.
        $restfmMessageRecords = $restfmMessage->getRecords();
        $record = NULL;         // @var RESTfmMessageRecord
        foreach($restfmMessageRecords as $record) {
            $record->setHref(
                $request->baseUri.'/'.
                        RESTfmUrl::encode($database).'/layout/'.
                        RESTfmUrl::encode($record['layout']).'.'.
                        $format.$queryString->build()
            );
        }

        $response->setStatus(\Tonic\Response::OK);
        $response->setRESTfmMessage($restfmMessage);
        return $response;
    }
}
