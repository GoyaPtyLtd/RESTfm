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
 * RESTfm layout element handler.
 *
 * @uri /{database}/layout/{layout}
 */
class uriLayout extends RESTfm\Resource {

    const URI = '/{database}/layout/{layout}';

    /**
     * Handle a GET request for this layout resource.
     *
     * A list of records will be returned containing all available fields
     * for this layout. Parameters may be used to limit and paginate the
     * records, preventing time outs.
     *
     * Query String Parameters:
     *  - RFMmax=<n>        : [default: 24] Maximum number of records to return.
     *  - RFMskip=<n>       : Number of records to skip past.
     *  - RFMsF<n>=<s>      : Search Field <n> for find criterion. Must have
     *                        matching Search Value.
     *  - RFMsV<n>=<s>      : Search Value <n> for find criterion. Must have
     *                        matching Search Field.
     *                    e.g.: MyField <100 AND MyField2 contains 'test':
     *                    RFMsF1=MyField&RFMsV1=<100&RFMsF2=MyField2&RFMsV2=test
     *  - RFMscript=<name>  : url encoded script name to be called after
     *                        result set is generated and sorted.
     *  - RFMscriptParam=<string> : (optional) url encoded parameter string to
     *                              pass to script.
     *  - RFMpreScript=<name> : url encoded script name to be called before
     *                          performing the find and sorting the result set.
     *  - RFMpreScriptParam=<string> : (optional) url encoded parameter string
     *                                 to pass to pre-script.
     *  - RFMmetaFieldOnly  : Set flag to return only field metadata from
     *                        layout (metaField), no record data.
     *  - RFMcontainer=<encoding> : [default: DEFAULT], BASE64, RAW
     *              DEFAULT - The container data URL to be fetched separately.
     *              BASE64  - Encode the container data in BASE64 as
     *                        <filename>;<base64 data>
     *  - RFMfind=<SQL query> : An SQL subset syntax that may include
     *                          SELECT, WHERE, ORDER BY, OFFSET, LIMIT
     *
     * @param RESTfm\Request $request
     * @param string $database
     *   From URI parsing: /{database}/layout/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}
     *
     * @return RESTfm\Response
     */
    function get($request, $database, $layout) {
        $database = RESTfm\Url::decode($database);
        $layout = RESTfm\Url::decode($layout);

        $backend = RESTfm\BackendFactory::make($request, $database);
        $opsLayout = $backend->makeOpsLayout($database, $layout);
        $restfmParameters = $request->getParameters();

        if (isset($restfmParameters->RFMmetaFieldOnly)) {
            $restfmMessage = $opsLayout->readMetaField();
            $response = new RESTfm\Response($request);
            $response->setStatus(RESTfm\Response::OK);
            $response->setMessage($restfmMessage);
            return $response;
        }

        // Identify search fields and values.
        $searchFields = $restfmParameters->getRegex('/^RFMsF\d+$/');
        $searchValues = $restfmParameters->getRegex('/^RFMsV\d+$/');
        if (count($searchFields) > 0) {
            foreach ($searchFields as $searchFieldKey => $searchField) {
                // Locate matching search value for search field by key name.
                $searchValueKey = str_replace('RFMsF', 'RFMsV', $searchFieldKey);
                if (isset($searchValues[$searchValueKey])) {
                    $opsLayout->addFindCriterion($searchField, $searchValues[$searchValueKey]);
                }
            }
        }

        // SQL-like query
        if (isset($restfmParameters->RFMfind)) {
            // Ensure we unset any basic find criterion from RFMsF*/RFMsV* first.
            $opsLayout->clearCriteria();
            $opsLayout->setSQLquery($restfmParameters->RFMfind);
        }

        // Allow script calling.
        if (isset($restfmParameters->RFMscript)) {
            $scriptParameters = NULL;
            if (isset($restfmParameters->RFMscriptParam)) {
                $scriptParameters = $restfmParameters->RFMscriptParam;
            }
            $opsLayout->setPostOpScript($restfmParameters->RFMscript, $scriptParameters);
        }
        if (isset($restfmParameters->RFMpreScript)) {
            $scriptParameters = NULL;
            if (isset($restfmParameters->RFMpreScriptParam)) {
                $scriptParameters = $restfmParameters->RFMpreScriptParam;
            }
            $opsLayout->setPreOpScript($restfmParameters->RFMpreScript, $scriptParameters);
        }

        // Determine requirements for container encoding.
        if (isset($restfmParameters->RFMcontainer)) {
            $containerEncoding = strtoupper($restfmParameters->RFMcontainer);
            if ($containerEncoding == 'BASE64') {
                $containerEncoding = $opsLayout::CONTAINER_BASE64;
            } elseif ($containerEncoding == 'RAW') {
                $containerEncoding = $opsLayout::CONTAINER_RAW;
            } else {
                $containerEncoding = $opsLayout::CONTAINER_DEFAULT;
            }
            $opsLayout->setContainerEncoding($containerEncoding);
        }

        // Determine skip and max returned results.
        $findSkip = $restfmParameters->RFMskip;
        $findMax = $restfmParameters->RFMmax;
        $findSkip = isset($findSkip) ? $findSkip : 0;
        $findSkip = ($findSkip === 'end') ? -1 : $findSkip;
        $findMax = isset($findMax) ? $findMax : 24;
        $opsLayout->setLimit($findSkip, $findMax);

        // Read data from layout.
        $restfmMessage = $opsLayout->read();

        $foundSetCount = $restfmMessage->getInfo('foundSetCount');

        // Adjust findSkip if we skipped to the end.
        if ($findSkip == -1) {
            $findSkip = $foundSetCount - $findMax;
            $findSkip = max(0, $findSkip);  // Ensure not less than zero.
        }

        // Adjust findSkip if larger than found set.
        if ($findSkip > $foundSetCount) {
            $findSkip = $foundSetCount;
        }

        // Info section.
        $restfmMessage->setInfo('skip', $findSkip);

        $response = new RESTfm\Response($request);
        $format = $response->format;
        $queryString = new RESTfm\QueryString(TRUE);

        $databaseEnc = RESTfm\Url::encode($database);
        $layoutEnc = RESTfm\Url::encode($layout);

        // Meta section.
        // Iterate records and set navigation hrefs.
        /** @var \RESTfm\Message\Message $restfmMessage */
        /** @var \RESTfm\Message\Record $record */
        foreach($restfmMessage->getRecords() as $record) {
            if ($record->getRecordId() === NULL) {
                continue;
            }
            $record->setHref(
                $request->baseUri.'/'.
                        $databaseEnc.'/layout/'.$layoutEnc.'/'.
                        RESTfm\Url::encode($record->getRecordId()).'.'.$format
            );
        }

        // Nav section.
        // Setup URI + query string for navigation links.

        // Calculate skip values
        $skipPrev = max(0, $findSkip - $findMax);
        $fetchCount = $restfmMessage->getInfo('fetchCount');
        $skipNext = $findSkip + $fetchCount;

        // Start nav link.
        unset($queryString->RFMskip);
        $restfmMessage->setNav('start',
                    $request->baseUri.'/'.$databaseEnc.'/layout/'.
                    $layoutEnc.'.'.$format.$queryString->build()
        );

        // Only build a next nav link if we have not exhausted the found set.
        if ($skipNext < $foundSetCount) {
            $queryString->RFMskip = $skipNext;
            $restfmMessage->setNav('next',
                        $request->baseUri.'/'.$databaseEnc.'/layout/'.
                        $layoutEnc.'.'.$format.$queryString->build()
            );
        }

        // Only build a prev nav link if we have skipped something.
        if ($findSkip != 0) {
            $queryString->RFMskip = $skipPrev;
            $restfmMessage->setNav('prev',
                        $request->baseUri.'/'.$databaseEnc.'/layout/'.
                        $layoutEnc.'.'.$format.$queryString->build()
            );
        }

        // End nav link.
        $queryString->RFMskip = $foundSetCount - 1;
        $restfmMessage->setNav('end',
                    $request->baseUri.'/'.$databaseEnc.'/layout/'.
                    $layoutEnc.'.'.$format.$queryString->build()
        );


        $response->setStatus(RESTfm\Response::OK);
        $response->setMessage($restfmMessage);
        return $response;
    }

    /**
     * Handle a POST request for this resource.
     *
     * A new record will be created from the provided data.
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
     *   From URI parsing: /{database}/layout/{layout}
     * @param string $layout
     *   From URI parsing: /{database}/layout/{layout}
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

        $restfmMessage = $opsRecord->createSingle($request->getMessage());

        $response = new RESTfm\Response($request);
        $format = $response->format;

        // Meta section.
        // Iterate records and set navigation hrefs.
        $record = NULL;         // @var \RESTfm\Message\Record
        foreach($restfmMessage->getRecords() as $record) {
            if ($record->getRecordId() === NULL) {
                continue;
            }
            $record->setHref(
                $request->baseUri.'/'.
                        RESTfm\Url::encode($database).'/layout/'.
                        RESTfm\Url::encode($layout).'/'.
                        RESTfm\Url::encode($record->getRecordId()).'.'.$format
            );
        }

        $response->setMessage($restfmMessage);
        $response->setStatus(RESTfm\Response::CREATED);
        return $response;
    }

}
