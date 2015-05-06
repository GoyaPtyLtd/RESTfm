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

/***
 *** WARNING: Not in use, out of date, will not function completely.
 *** WARNING: No repetitions support.
 ***/

require_once 'RESTfm/RESTfmResource.php';
require_once 'RESTfm/RESTfmResponse.php';
require_once 'RESTfm/FileMakerResponseException.php';
require_once 'RESTfm/RESTfmRecordID.php';
require_once 'RESTfm/RESTfmQueryString.php';

/**
 * RESTfm Field handler for Tonic
 *
 * @uri /{database}/layout/{layout}/{rawRecordID}/{field}
 */
class uriField extends RESTfmResource {

    const URI = '/{database}/layout/{layout}/{rawRecordID}/{field}';

    /**
     * Handle a GET request for this resource
     *
     * @param RESTfmRequest $request
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
    function get($request, $database, $layout, $rawRecordID, $field) {

        $response = new RESTfmResponse($request);
        $recordID = new RESTfmRecordID($rawRecordID);

        $record = $recordID->getRecord(urldecode($database), urldecode($layout));

        if (FileMaker::isError($record)) {
            throw new FileMakerResponseException($record);
        }

        $format = $response->format;

        $resourceData = new ResourceData();

        $urldecodeField = urldecode($field);

        // Dig out field meta data from field objects in layout object returned
        // by record object!
        $layoutResult = $record->getLayout();
        $fieldMeta = array();
        $fieldResult = $layoutResult->getField($urldecodeField);
        if (FileMaker::isError($fieldResult)) {
            throw new FileMakerResponseException($fieldResult);
        }
        $fieldMeta['autoEntered'] = $fieldResult->isAutoEntered() ? 1 : 0;
        $fieldMeta['global'] = $fieldResult->isGlobal() ? 1 : 0;
        $fieldMeta['maxRepeat'] = $fieldResult->getRepetitionCount();
        $fieldMeta['resultType'] = $fieldResult->getResult();
        //$fieldMeta['type'] = $fieldResult->getType();

        $fieldResultType = $fieldMeta['resultType'];

        $resourceData->pushFieldMeta($urldecodeField, $fieldMeta);


        // Process field and push data.
        $recordRow = array();
        $href = $request->baseUri.'/'.$database.'/layout/'.$layout.'/'.$recordID.'/'.$field.'.'.$format;
        if ($fieldResultType == 'container' && method_exists($FM, 'getContainerDataURL')) {
            // Note: FileMaker::getContainerDataURL() only exists in the FMSv12 PHP API
            $recordRow[$urldecodeField] = $FM->getContainerDataURL($record->getField($urldecodeField));
        } else {
            $recordRow[$urldecodeField] = $record->getFieldUnencoded($urldecodeField);
        }
        $resourceData->pushData($recordRow, $href, urldecode($recordID));

        $response->setStatus(Response::OK);
        $response->setResourceData($resourceData);
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
     * @param RESTfmRequest $request
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
     * @param RESTfmRequest $request
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
