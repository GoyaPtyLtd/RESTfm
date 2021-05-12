<?php
/**
 *  RESTfm - FileMaker RESTful Web Service
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
 * RESTfm database collection (Root URI) handler for Tonic.
 *
 * @uri
 */
class uriRoot extends RESTfm\Resource {

    const URI = '';

    /**
     * Handle a GET request for this resource
     *
     * Query String Parameters:
     *  - RFMlink=layout|script : Provide a link that skips directly to layout
     *          or script. By default the link returned goes to the database
     *          static page with separate layout and script links, to maintain
     *          the REST URI hierarchy.
     *
     * @param RESTfm\Request $request
     *
     * @return Response
     */
    function get($request) {
        $backend = RESTfm\BackendFactory::make($request);
        $opsDatabase = $backend->makeOpsDatabase();
        $restfmMessage = $opsDatabase->readDatabases();

        $queryString = new RESTfm\QueryString(TRUE);

        $response = new RESTfm\Response($request);
        $format = $response->format;

        $RFMlink = NULL;
        if (isset($queryString->RFMlink)) {
            $RFMlink = $queryString->RFMlink;
            unset($queryString->RFMlink);
        }

        // Inject PDO databases, if any.
        // If we get this far then FM authentication was successful so this
        // list wont be open access with FM guest access disabled.
        if (RESTfm\Config::checkVar('databasePDOMap')) {
            $pdos = RESTfm\Config::getVar('databasePDOMap');
            foreach ($pdos as $dbMapName => $dsn) {
                $restfmMessage->addRecord(new \RESTfm\Message\Record(
                    NULL, NULL, array('database' => $dbMapName)
                ));
            }
        }

        // Iterate records and set navigation hrefs.
        $restfmMessageRecords = $restfmMessage->getRecords();
        $record = NULL;         // @var \RESTfm\Message\Record
        foreach($restfmMessageRecords as $record) {
            $database = $record['database'];
            $href = $request->baseUri.'/'.RESTfm\Url::encode($database).
                    '.'.$format.$queryString->build();
            if (isset($RFMlink)) {
                if ($RFMlink == 'layout') {
                    $href = $request->baseUri.'/'.RESTfm\Url::encode($database).
                            '/layout.'.$format.$queryString->build();
                } elseif ($RFMlink == 'script') {
                    $href = $request->baseUri.'/'.RESTfm\Url::encode($database).
                            '/script.'.$format.$queryString->build();
                }
            }
            $record->setHref($href);
        }

        $response->setStatus(RESTfm\Response::OK);
        $response->setMessage($restfmMessage);
        return $response;
    }

};
