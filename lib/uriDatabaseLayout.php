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
require_once 'RESTfm/QueryString.php';

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
        $restfmData = $opsDatabase->readLayouts();

        $queryString = new QueryString(TRUE);

        $response = new RESTfmResponse($request);
        $format = $response->format;

        // Iterate 'data' section rows and insert navigation hrefs into
        // matching 'meta' section row.
        $restfmData->setIteratorSection('data');
        foreach($restfmData as $index => $row) {
            $href = $request->baseUri.'/'.
                        RESTfmUrl::encode($database).
                        '/layout/'.RESTfmUrl::encode($row['layout']).'.'.$format.
                        $queryString->build();

            $restfmData->setSectionData2nd('meta', $index, 'href', $href);
        }

        $response->setStatus(Response::OK);
        $response->setData($restfmData);
        return $response;
    }
}
