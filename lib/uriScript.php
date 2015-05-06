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
 * RESTfm script element handler.
 *
 * @uri /{database}/script/{script}/{layout}
 */
class uriScript extends RESTfmResource {

    const URI = '/{database}/script/{script}/{layout}';

    /**
     * Handle a GET request for this script resource.
     *
     * A list of records will be returned containing all records in the scripts
     * found set.
     *
     * Query String Parameters:
     *  - RFMscriptParam=<string> : (optional) url encoded parameter string
     *                              to pass to script.
     *  - RFMsuppressData : set flag to suppress 'data' section from response.
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/script/{script}/{layout}
     * @param string $script
     *   From URI parsing: /{database}/script/{script}/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/script/{script}/{layout}
     *   All scripts require a layout context to be executed in.
     *
     * @return Response
     */
    function get($request, $database, $script, $layout) {
        $database = RESTfmUrl::decode($database);
        $script = RESTfmUrl::decode($script);
        $layout = RESTfmUrl::decode($layout);

        $backend = BackendFactory::make($request, $database);
        $opsRecord = $backend->makeOpsRecord($database, $layout);
        $restfmParameters = $request->getRESTfmParameters();

        $scriptParameter = NULL;
        if (isset($restfmParameters->RFMscriptParam)) {
            $scriptParameter = $restfmParameters->RFMscriptParam;
        }

        if (isset($restfmParameters->RFMsuppressData)) {
            $opsRecord->setSuppressData(TRUE);
        }

        $restfmData = $opsRecord->callScript($script, $scriptParameter);

        $response = new RESTfmResponse($request);
        $format = $response->format;

        // Meta section.
        // Add hrefs for recordIDs.
        $restfmData->setIteratorSection('meta');
        foreach($restfmData as $index => $row) {
            $href = $request->baseUri.'/'.
                        RESTfmUrl::encode($database).'/layout/'.
                        RESTfmUrl::encode($layout).'/'.
                        RESTfmUrl::encode($row['recordID']).'.'.$format;
            $restfmData->setSectionData2nd('meta', $index, 'href', $href);
        }

        $response->setData($restfmData);
        $response->setStatus(Response::OK);

        return $response;
    }

};
