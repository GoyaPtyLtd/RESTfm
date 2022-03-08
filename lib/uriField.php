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

use RESTfm\FieldResponse;

/**
 * RESTfm Field handler for Tonic
 *
 * @uri /{database}/layout/{layout}/{rawRecordID}/{field}
 */
class uriField extends RESTfm\Resource {

    const URI = '/{database}/layout/{layout}/{rawRecordID}/{field}';

    /**
     * Handle a GET request for this resource
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $field
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     *
     * @return FieldResponse
     */
    function get($request, $database, $layout, $rawRecordID, $field) {
        $database = RESTfm\Url::decode($database);
        $layout = RESTfm\Url::decode($layout);
        $rawRecordID = RESTfm\Url::decode($rawRecordID);
        $field = RESTfm\Url::decode($field);

        $backend = RESTfm\BackendFactory::make($request, $database);

        $opsField = $backend->makeOpsField($database, $layout);
        $restfmParameters = $request->getParameters();

        // Determine requirements for container encoding.
        if (isset($restfmParameters->RFMcontainer)) {
            $containerEncoding = strtoupper($restfmParameters->RFMcontainer);
            if ($containerEncoding == 'BASE64') {
                $containerEncoding = $opsField::CONTAINER_BASE64;
            } elseif ($containerEncoding == 'RAW') {
                $containerEncoding = $opsField::CONTAINER_RAW;
            } else {
                $containerEncoding = $opsField::CONTAINER_DEFAULT;
            }
            $opsField->setContainerEncoding($containerEncoding);
        }

        $response = new RESTfm\FieldResponse($request);

        $opsField->read($response, $rawRecordID, $field);

        $response->setStatus(RESTfm\Response::OK);
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
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $field
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     *
     * @return Response
     */
    function put($request, $database, $layout, $rawRecordID, $field) {

        // This is identical to uriRecord::put.
        // In theory we could (should ?) be enforcing only this single field
        // but it makes no serious difference.
        return uriRecord::put($request, $database, $layout, $rawRecordID);
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
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     * @param string $field
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}
     *
     * @return Response
     */
    function delete($request, $database, $layout, $rawRecordID, $field) {

        // Since we can't delete a field per se, we delete it's contents.
        // This is identical to $this->put() but with empty data for $field.

        // Inject an empty value for data $field in $request->parseData.
        $request->parsedData = array( 'data' => array() );
        $request->parsedData['data'][] = array( urldecode($field) => NULL );

        return $this->put($request, $database, $layout, $rawRecordID, $field);
    }

}
