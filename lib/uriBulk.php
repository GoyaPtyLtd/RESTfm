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
require_once 'RESTfm/RESTfmParameters.php';
require_once 'RESTfm/RESTfmData.php';
require_once 'RESTfm/BackendFactory.php';

/**
 * Bulk record CRUD operations resource class.
 *
 * All bulk operations expect a submitted document containing recordIDs and/or
 * data. See relevant operation for exact requirements.
 *
 * @uri /{database}/bulk/{layout}
 */
class uriBulk extends RESTfmResource {

    const URI = '/{database}/bulk/{layout}';

    /**
     * Create bulk records with record data provided in 'data' section of
     * submitted document.
     *
     * Query String Parameters:
     *  - RFMscript=<name>  : url encoded script name to be called after
     *                        result set is generated and sorted.
     *  - RFMscriptParam=<string> : (optional) url encoded parameter string to
     *                              pass to script.
     *  - RFMpreScript=<name> : url encoded script name to be called before
     *                          performing the find and sorting the result set.
     *  - RFMpreScriptParam=<string> : (optional) url encoded parameter string
     *                                 to pass to pre-script.
     *  - RFMsuppressData : set flag to suppress 'data' section from response.
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/bulk/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/bulk/{layout}
     *
     * @return RESTfmResponse
     */
    function post($request, $database, $layout) {
        $database = RESTfmUrl::decode($database);
        $layout = RESTfmUrl::decode($layout);

        $backend = BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);

        $restfmParameters = $request->getRESTfmParameters();

        // Allow script calling.
        if (isset($restfmParameters->RFMscript)) {
            $scriptParameters = NULL;
            if (isset($restfmParameters->RFMscriptParam)) {
                $scriptParameters = $restfmParameters->RFMscriptParam;
            }
            $opsRecord->setPostOpScript($restfmParameters->RFMscript, $scriptParameters);
        }
        if (isset($restfmParameters->RFMpreScript)) {
            $scriptParameters = NULL;
            if (isset($restfmParameters->RFMpreScriptParam)) {
                $scriptParameters = $restfmParameters->RFMpreScriptParam;
            }
            $opsRecord->setPreOpScript($restfmParameters->RFMpreScript, $scriptParameters);
        }

        if (isset($restfmParameters->RFMsuppressData)) {
            $opsRecord->setSuppressData(TRUE);
        }

        $restfmData = $opsRecord->createBulk($request->getRESTfmData());

        $response = new RESTfmResponse($request);

        $response->setData($restfmData);

        if ($restfmData->sectionExists('multistatus')) {
            $response->setStatus(207, 'Multi-status');
        } else {
            $response->setStatus(Response::OK);
        }

        return $response;
    }

    /**
     * Read bulk recordIDs as specified in 'meta' section of submitted
     * document.
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/bulk/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/bulk/{layout}
     *
     * @return RESTfmResponse
     */
    function get($request, $database, $layout) {
        $database = RESTfmUrl::decode($database);
        $layout = RESTfmUrl::decode($layout);

        $backend = BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);

        $restfmData = $opsRecord->readBulk($request->getRESTfmData());

        $response = new RESTfmResponse($request);

        $response->setData($restfmData);

        if ($restfmData->sectionExists('multistatus')) {
            $response->setStatus(207, 'Multi-status');
        } else {
            $response->setStatus(Response::OK);
        }

        return $response;
    }

    /**
     * Update bulk recordIDs as specified in 'meta' section with record data
     * provided in 'data' section of submitted document.
     *
     * Query String Parameters:
     *  - RFMscript=<name>  : url encoded script name to be called after
     *                        result set is generated and sorted.
     *  - RFMscriptParam=<string> : (optional) url encoded parameter string to
     *                              pass to script.
     *  - RFMpreScript=<name> : url encoded script name to be called before
     *                          performing the find and sorting the result set.
     *  - RFMpreScriptParam=<string> : (optional) url encoded parameter string
     *                                 to pass to pre-script.
     *  - RFMelsePOST : If this record does not exist, perform a POST (create)
     *                  instead. aka RFMelseCreate.
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/bulk/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/bulk/{layout}
     *
     * @return RESTfmResponse
     */
    function put($request, $database, $layout) {
        $database = RESTfmUrl::decode($database);
        $layout = RESTfmUrl::decode($layout);

        $backend = BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);

        $restfmParameters = $request->getRESTfmParameters();

        // Allow script calling and other parameters.
        if (isset($restfmParameters->RFMscript)) {
            $scriptParameters = NULL;
            if (isset($restfmParameters->RFMscriptParam)) {
                $scriptParameters = $restfmParameters->RFMscriptParam;
            }
            $opsRecord->setPostOpScript($restfmParameters->RFMscript, $scriptParameters);
        }
        if (isset($restfmParameters->RFMpreScript)) {
            $scriptParameters = NULL;
            if (isset($restfmParameters->RFMpreScriptParam)) {
                $scriptParameters = $restfmParameters->RFMpreScriptParam;
            }
            $opsRecord->setPreOpScript($restfmParameters->RFMpreScript, $scriptParameters);
        }
        if (isset($restfmParameters->RFMelsePOST) || isset($restfmParameters->RFMelseCreate)) {
            $opsRecord->setUpdateElseCreate();
        }

        $restfmData = $opsRecord->updateBulk($request->getRESTfmData());

        $response = new RESTfmResponse($request);

        $response->setData($restfmData);

        if ($restfmData->sectionExists('multistatus')) {
            $response->setStatus(207, 'Multi-status');
        } else {
            $response->setStatus(Response::OK);
        }

        return $response;
    }

    /**
     * Delete bulk recordIDs as specified in 'meta' section of submitted
     * document.
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/bulk/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/bulk/{layout}
     *
     * @return RESTfmResponse
     */
    function delete($request, $database, $layout) {
        $database = RESTfmUrl::decode($database);
        $layout = RESTfmUrl::decode($layout);

        $backend = BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);

        $restfmData = $opsRecord->deleteBulk($request->getRESTfmData());

        $response = new RESTfmResponse($request);

        $response->setData($restfmData);

        if ($restfmData->sectionExists('multistatus')) {
            $response->setStatus(207, 'Multi-status');
        } else {
            $response->setStatus(Response::OK);
        }

        return $response;
    }
};
