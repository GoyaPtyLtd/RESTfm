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
 * RESTfm script element handler.
 *
 * @uri /{database}/script/{script}/{layout}
 */
class uriScript extends RESTfm\Resource {

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
     * @param RESTfm\Request $request
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
        $database = RESTfm\Url::decode($database);
        $script = RESTfm\Url::decode($script);
        $layout = RESTfm\Url::decode($layout);

        $backend = RESTfm\BackendFactory::make($request, $database);
        $opsRecord = $backend->makeOpsRecord($database, $layout);
        $restfmParameters = $request->getParameters();

        $scriptParameter = NULL;
        if (isset($restfmParameters->RFMscriptParam)) {
            $scriptParameter = $restfmParameters->RFMscriptParam;
        }

        if (isset($restfmParameters->RFMsuppressData)) {
            $opsRecord->setSuppressData(TRUE);
        }

        $restfmMessage = $opsRecord->callScript($script, $scriptParameter);

        $response = new RESTfm\Response($request);
        $format = $response->format;

        // Meta section.
        // Iterate records and set navigation hrefs.
        $record = NULL;         // @var \RESTfm\Message\Record
        foreach($restfmMessage->getRecords() as $record) {
            $record->setHref(
                $request->baseUri.'/'.
                        RESTfm\Url::encode($database).'/layout/'.
                        RESTfm\Url::encode($layout).'/'.
                        RESTfm\Url::encode($record->getRecordId()).'.'.$format
            );
        }

        $response->setMessage($restfmMessage);
        $response->setStatus(RESTfm\Response::OK);

        return $response;
    }

};
