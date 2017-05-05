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
class uriDatabaseLayout extends RESTfm\Resource {

    const URI = '/{database}/layout';

    /**
     * Handle a GET request for this resource
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/layout
     *
     * @return Response
     */
    function get($request, $database) {
        $database = RESTfm\Url::decode($database);

        $backend = RESTfm\BackendFactory::make($request, $database);
        $opsDatabase = $backend->makeOpsDatabase($database);
        $restfmMessage = $opsDatabase->readLayouts();

        $queryString = new RESTfm\QueryString(TRUE);

        $response = new RESTfm\Response($request);
        $format = $response->format;

        // Iterate records and set navigation hrefs.
        $restfmMessageRecords = $restfmMessage->getRecords();
        $record = NULL;         // @var \RESTfm\Message\Record
        foreach($restfmMessageRecords as $record) {
            $record->setHref(
                $request->baseUri.'/'.
                        RESTfm\Url::encode($database).'/layout/'.
                        RESTfm\Url::encode($record['layout']).'.'.
                        $format.$queryString->build()
            );
        }

        $response->setStatus(RESTfm\Response::OK);
        $response->setMessage($restfmMessage);
        return $response;
    }
}
