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
 * Bulk record CRUD operations resource class.
 *
 * All bulk operations expect a submitted document containing recordIDs and/or
 * data. See relevant operation for exact requirements.
 *
 * @uri /{database}/bulk/{layout}
 */
class uriBulk extends RESTfm\Resource {

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
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/bulk/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/bulk/{layout}
     *
     * @return RESTfm\Response
     */
    function post($request, $database, $layout) {
        $database = RESTfm\Url::decode($database);
        $layout = RESTfm\Url::decode($layout);

        $backend = RESTfm\BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);

        $restfmParameters = $request->getParameters();

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

        $restfmMessage = $opsRecord->createBulk($request->getMessage());

        $response = new RESTfm\Response($request);

        $response->setMessage($restfmMessage);

        if ($restfmMessage->getMultistatusCount() > 0) {
            $response->setStatus(207, 'Multi-status');
        } else {
            $response->setStatus(RESTfm\Response::OK);
        }

        return $response;
    }

    /**
     * Read bulk recordIDs as specified in 'meta' section of submitted
     * document.
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/bulk/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/bulk/{layout}
     *
     * @return RESTfm\Response
     */
    function get($request, $database, $layout) {
        $database = RESTfm\Url::decode($database);
        $layout = RESTfm\Url::decode($layout);

        $backend = RESTfm\BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);

        $restfmMessage = $opsRecord->readBulk($request->getMessage());

        $response = new RESTfm\Response($request);

        $response->setMessage($restfmMessage);

        if ($restfmMessage->getMultistatusCount() > 0) {
            $response->setStatus(207, 'Multi-status');
        } else {
            $response->setStatus(RESTfm\Response::OK);
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
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/bulk/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/bulk/{layout}
     *
     * @return RESTfm\Response
     */
    function put($request, $database, $layout) {
        $database = RESTfm\Url::decode($database);
        $layout = RESTfm\Url::decode($layout);

        $backend = RESTfm\BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);

        $restfmParameters = $request->getParameters();

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

        $restfmMessage = $opsRecord->updateBulk($request->getMessage());

        $response = new RESTfm\Response($request);

        $response->setMessage($restfmMessage);

        if ($restfmMessage->getMultistatusCount() > 0) {
            $response->setStatus(207, 'Multi-status');
        } else {
            $response->setStatus(RESTfm\Response::OK);
        }

        return $response;
    }

    /**
     * Delete bulk recordIDs as specified in 'meta' section of submitted
     * document.
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/bulk/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/bulk/{layout}
     *
     * @return RESTfm\Response
     */
    function delete($request, $database, $layout) {
        $database = RESTfm\Url::decode($database);
        $layout = RESTfm\Url::decode($layout);

        $backend = RESTfm\BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);

        $restfmMessage = $opsRecord->deleteBulk($request->getMessage());

        $response = new RESTfm\Response($request);

        $response->setMessage($restfmMessage);

        if ($restfmMessage->getMultistatusCount() > 0) {
            $response->setStatus(207, 'Multi-status');
        } else {
            $response->setStatus(RESTfm\Response::OK);
        }

        return $response;
    }
};
