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

namespace RESTfm;

/**
 * RESTfm Field Response class.
 */
class FieldResponse extends Response {

    /**
     * Output the response.
     *
     * Overrides parent method.
     */
    public function output() {
        $this->addHeader('X-RESTfm-Version', Version::getVersion());
        $this->addHeader('X-RESTfm-Protocol', Version::getProtocol());
        $this->addHeader('X-RESTfm-Status', $this->code);
        $this->addHeader('X-RESTfm-Reason', $this->reason);
        $this->addHeader('X-RESTfm-Method', $this->request->method);

        // Check if we need to authorise this origin (CORS)
        if (isset($_SERVER["HTTP_ORIGIN"]) && Config::checkVar('origins', 'allowed')) {
            $configOrigins = Config::getVar('origins', 'allowed');
            $request_origin = $_SERVER['HTTP_ORIGIN'];
            $allow_origin = null;
            if (in_array('*', $configOrigins)) {
                $allow_origin = '*';
            } else {
                // Case insensitive match for allowed origin.
                foreach ($configOrigins as $origin) {
                    if (strtolower($request_origin) == strtolower($origin)) {
                        $allow_origin = $request_origin;
                    }
                }
            }
            if ($allow_origin != null) {
                $this->addHeader('Access-Control-Allow-Origin', $allow_origin);
            }
        }

        // Ensure we have a response body!
        if ($this->body == NULL) {
            $this->body = '';
        }

        // Modification of original tonic version.
        if (php_sapi_name() != 'cli' && !headers_sent()) {

            if ($this->reason != '') {
                header('HTTP/1.1 ' . $this->code . ' ' . $this->reason);
            } else {
                header('HTTP/1.1 ' . $this->code);
            }
            foreach ($this->headers as $header => $value) {
                header($header.': '.$value);
            }
        }

        if (strtoupper($this->request->method) !== 'HEAD') {
            echo $this->body;
        }
    }

    /**
     * Set response body, and content metadata.
     *
     * @param string $data
     * @param string $contentType
     * @param string $contentLength
     */
    public function setBody($data, $contentType = NULL, $contentLength = NULL) {
        $this->body = $data;

        if ($contentType !== NULL) {
            $this->addHeader('Content-Type', $contentType);
        }

        if ($contentLength !== NULL) {
            $this->addHeader('Content-Length', $contentLength);
        } else {
            $this->addHeader('Content-Length', strlen($data));
        }
    }

}
