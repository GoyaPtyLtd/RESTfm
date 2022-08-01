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
 * RESTfm Field handler for Tonic
 *
 * @uri /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
 */
class uriFieldFilename extends RESTfm\Resource {

    const URI = '/{database}/layout/{layout}/{rawRecordID}/{field}/{filename}';

    /**
     * Handle a GET request for this resource.
     *
     * This is identical to uriField::get, except for the extra $filename
     * parameter. This allows the use of $filename in HTTP Content-Disposition
     * Header in the same way that RFMfilename works with uriField::get.
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     * @param string $field
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     * @param string $filename
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     *
     * @throws RESTfm\ResponseException
     *   In all cases (returning field data, or on error)
     */
    function get($request, $database, $layout, $rawRecordID, $field, $filename) {
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

        // Determine container filename.
        if (!empty($filename)) {
            $opsField->setContainerFilename($filename);
        }

        $opsField->setContainerMimeType($request->getFormat());

        $response = new RESTfm\FieldResponse($request);

        $opsField->read($response, $rawRecordID, $field);

        $response->setStatus(RESTfm\Response::OK);
        return $response;
    }

    /**
     * Handle a PUT request for this resource
     *
     * This is identical to uriField::put, except for the extra $filename
     * parameter. This allows the use of $filename in HTTP Content-Disposition
     * Header in the same way that RFMfilename works with uriField::put.
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     * @param string $field
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     * @param string $filename
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{filename}
     *
     * @return RESTfm\Response
     */
    function put($request, $database, $layout, $rawRecordID, $field, $filename) {
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

        // Determine container filename.
        if (!empty($filename)) {
            $opsField->setContainerFilename($filename);
        }

        $opsField->setContainerMimeType($request->getFormat());

        $opsField->update($rawRecordID, $field, $request->getData());

        $response = new RESTfm\Response($request);
        $response->setStatus(RESTfm\Response::OK);
        return $response;
    }

}
