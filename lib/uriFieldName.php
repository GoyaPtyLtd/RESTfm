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
 * @uri /{database}/layout/{layout}/{rawRecordID}/{field}/{name}
 */
class uriFieldName extends RESTfm\Resource {

    const URI = '/{database}/layout/{layout}/{rawRecordID}/{field}/{name}';

    /**
     * Handle a GET request for this resource.
     * This is identical to uriField::get, except for the extra $name parameter
     * that does nothing other than allow $name to be automatically used when
     * GETting a raw container.
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{name}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{name}
     * @param string $rawRecordID
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{name}
     * @param string $field
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{name}
     * @param string $name
     *   From URI parsing: /{database}/layout/{layout}/{rawRecordID}/{field}/{name}
     *
     * @throws RESTfm\ResponseException
     *   In all cases (returning field data, or on error)
     */
    function get($request, $database, $layout, $rawRecordID, $field, $name) {
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
        if (!empty($name)) {
            $opsField->setContainerFilename($name);
        }

        $response = new RESTfm\FieldResponse($request);

        $opsField->read($response, $rawRecordID, $field);

        $response->setStatus(RESTfm\Response::OK);
        return $response;
    }

}
