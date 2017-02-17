<?php
/**
 *  RESTfm - FileMaker RESTful Web Service
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
 * RESTfm database collection (Root URI) handler for Tonic.
 *
 * @uri
 */
class uriRoot extends RESTfmResource {

    const URI = '/';

    /**
     * Handle a GET request for this resource
     *
     * Query String Parameters:
     *  - RFMlink=layout|script : Provide a link that skips directly to layout
     *          or script. By default the link returned goes to the database
     *          static page with seperate layout and script links, to maintain
     *          the REST URI hierarchy.
     *
     * @param RESTfmRequest $request
     *
     * @return Response
     */
    function get($request) {
        $backend = BackendFactory::make($request);
        $opsDatabase = $backend->makeOpsDatabase();
        $restfmMessage = $opsDatabase->readDatabases();

        $queryString = new RESTfmQueryString(TRUE);

        $response = new RESTfm\Response($request);
        $format = $response->format;

        $RFMlink = NULL;
        if (isset($queryString->RFMlink)) {
            $RFMlink = $queryString->RFMlink;
            unset($queryString->RFMlink);
        }

        // Inject local PDO databases if any.
        // If we get this far then FM authentication was successfull so this
        // list wont be open access with FM guest access disabled.
        if (RESTfmConfig::checkVar('databasePDOMap')) {
            $pdos = RESTfmConfig::getVar('databasePDOMap');
            foreach ($pdos as $dbMapName => $dsn) {
                $restfmMessage->addRecord(new RESTfmMessageRecord(
                    NULL, NULL, array('database' => $dbMapName)
                ));
            }
        }

        // Iterate records and set navigation hrefs.
        $restfmMessageRecords = $restfmMessage->getRecords();
        $record = NULL;         // @var RESTfmMessageRecord
        foreach($restfmMessageRecords as $record) {
            $database = $record['database'];
            $href = $request->baseUri.'/'.RESTfmUrl::encode($database).
                    '.'.$format.$queryString->build();
            if (isset($RFMlink)) {
                if ($RFMlink == 'layout') {
                    $href = $request->baseUri.'/'.RESTfmUrl::encode($database).
                            '/layout.'.$format.$queryString->build();
                } elseif ($RFMlink == 'script') {
                    $href = $request->baseUri.'/'.RESTfmUrl::encode($database).
                            '/script.'.$format.$queryString->build();
                }
            }
            $record->setHref($href);
        }

        $response->setStatus(\Tonic\Response::OK);
        $response->setRESTfmMessage($restfmMessage);
        return $response;
    }

};
