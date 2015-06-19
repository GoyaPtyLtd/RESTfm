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
require_once 'RESTfm/RESTfmData.php';

/**
 * RESTfm Record handler for Tonic
 *
 * @uri /{database}/layout/{layout}/{rawRecordID}
 */
class uriRecord extends RESTfmResource {

    const URI = '/{database}/layout/{layout}/{rawRecordID}';

    /**
     * Handle a GET request for this resource
     *
     * Query String Parameters:
     *  - RFMcontainer=<encoding> : [default: DEFAULT], BASE64, RAW
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     *
     * @return Response
     */
    function get($request, $database, $layout, $rawRecordID) {
        $database = RESTfmUrl::decode($database);
        $layout = RESTfmUrl::decode($layout);
        $rawRecordID = RESTfmUrl::decode($rawRecordID);

        $backend = BackendFactory::make($request, $database);

        $opsRecord = $backend->makeOpsRecord($database, $layout);
        $restfmParameters = $request->getRESTfmParameters();

        // Determine requirements for container encoding.
        if (isset($restfmParameters->RFMcontainer)) {
            $containerEncoding = strtoupper($restfmParameters->RFMcontainer);
            if ($containerEncoding == 'BASE64') {
                $containerEncoding = $opsRecord::CONTAINER_BASE64;
            } elseif ($containerEncoding == 'RAW') {
                $containerEncoding = $opsRecord::CONTAINER_RAW;
            } else {
                $containerEncoding = $opsRecord::CONTAINER_DEFAULT;
            }
            $opsRecord->setContainerEncoding($containerEncoding);
        }

        $restfmData = $opsRecord->readSingle($rawRecordID);

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

    /**
     * Handle a PUT request for this resource
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
     *  - RFMappend : Append submitted data to existing field data instead of
     *                the default overwrite.
     *  - RFMelsePOST : If this record does not exist, perform a POST (create)
     *                  instead. aka RFMelseCreate.
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     *
     * @return Response
     */
    function put($request, $database, $layout, $rawRecordID) {
        $database = RESTfmUrl::decode($database);
        $layout = RESTfmUrl::decode($layout);
        $rawRecordID = RESTfmUrl::decode($rawRecordID);

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
        if (isset($restfmParameters->RFMappend)) {
            $opsRecord->setAppend();
        }
        if (isset($restfmParameters->RFMelsePOST) || isset($restfmParameters->RFMelseCreate)) {
            $opsRecord->setUpdateElseCreate();
        }

        $restfmData = $opsRecord->updateSingle($request->getRESTfmData(), $rawRecordID);

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

    /**
     * Handle a DELETE request for this resource
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
     *
     * @param RESTfmRequest $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}
     *
     * @return Response
     */
    function delete($request, $database, $layout, $rawRecordID) {
        $database = RESTfmUrl::decode($database);
        $layout = RESTfmUrl::decode($layout);
        $rawRecordID = RESTfmUrl::decode($rawRecordID);

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

        $restfmData = $opsRecord->deleteSingle($request->getRESTfmData(), $rawRecordID);

        $response = new RESTfmResponse($request);
        $response->setData($restfmData);
        $response->setStatus(Response::OK);
        return $response;
    }
}
