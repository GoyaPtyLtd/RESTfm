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

namespace RESTfm\BackendFileMakerDataApi;

/**
 * Reperesents a connection between PHP and a FileMaker Data API Server.
 */
class FileMakerDataApi {

    /**
     * @var resource
     *  Curl Handle.
     */
    private $_curlHandle = NULL;

    /**
     * @var string
     */
    private $_hostspec = NULL;

    /**
     * @var string
     */
    private $_username = NULL;

    /**
     * @var string
     */
    private $_password = NULL;

    /**
     * @var string
     */
    private $_solution = NULL;

    /**
     * @var string
     */
    private $_layout = NULL;

    /**
     * @var string
     */
    private $_token = NULL;

    /**
     * @param string $hostspec
     *  Base URL for FM Data API Server e.g. 'http://127.0.0.1:80'
     * @param string $solution
     *  The solution is hard coded into the RESTfm.ini.php map
     * @param string $username
     *  Optional username.
     * @param string $password
     *  Optional password.
     */
    public function __construct ($hostspec, $solution, $username = NULL, $password = NULL) {
        $ch = curl_init();
        $options = array(
            CURLOPT_USERAGENT       => 'RESTfm FileMaker Data API Backend',
        );
        curl_setopt_array($ch, $options);

        $this->_curlHandle = $ch;
        $this->_hostspec = $hostspec;
        $this->_solution = $solution;
        $this->_username = $username;
        $this->_password = $password;
    }

    /**
     * Cleanup.
     */
    public function close () {
        if ($this->_token == NULL) {
            return;
        }

        $ch = $this->_curlHandle;

        $options = array(
            CURLOPT_URL         => $this->_hostspec . '/fmi/rest/api/auth/' . $this->_solution,
            CURLOPT_SSL_VERIFYPEER  => FALSE,        // FIXME
            CURLOPT_FAILONERROR     => FALSE,
            CURLOPT_HEADER          => FALSE,
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_FOLLOWLOCATION  => FALSE,   // Redirects don't work.
                                                // Must use https in hostspec.
            CURLOPT_CUSTOMREQUEST   => 'DELETE',
            CURLOPT_HTTPHEADER => array(
                        'FM-Data-token: ' . $this->_token,
            ),
        );

        // Apply options.
        curl_setopt_array($ch, $options);

        // Submit the requested operation to FileMaker Data API Server.
        $result = curl_exec($ch);

        //echo "Closing result: ";
        //echo $result;

        curl_close($this->_curlHandle);
    }

    /**
     * Connect to the given layout using the hostspec, solution and
     * credentials provided at construction.
     *
     * @throws FileMakerDataApiResponseException
     */
    public function connect ($layout) {
        $this->_layout = $layout;

        if ($this->_token !== NULL) {
            // Already authenticated.
            return;
        }

        $ch = $this->_curlHandle;

        $options = array(
            CURLOPT_URL         => $this->_hostspec . '/fmi/rest/api/auth/' . $this->_solution,
            CURLOPT_SSL_VERIFYPEER  => FALSE,        // FIXME
            CURLOPT_FAILONERROR     => FALSE,
            CURLOPT_HEADER          => FALSE,
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_FOLLOWLOCATION  => FALSE,   // Redirects don't work.
                                                // Must use https in hostspec.
        );

        $data = array(
            'user'      => $this->_username,
            'password'  => $this->_password,
            'layout'    => $this->_layout,
        );

        $json = json_encode($data);

        $options = $options + array(
                CURLOPT_HTTPHEADER => array(
                            'Content-Length: ' . strlen($json),
                            'Content-Type: application/json; charset=UTF-8',
                ),
                CURLOPT_POSTFIELDS =>  $json,
        );

        // Apply options.
        curl_setopt_array($ch, $options);

        // Submit the requested operation to FileMaker Data API Server.
        $result = curl_exec($ch);

        // Throw an exception if cURL has errors.
        if(curl_errno($ch)) {
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);

            throw new FileMakerDataApiResponseException($curlError, $curlErrno);
        }

        // Decode JSON response into $response reference variable.
        $response = json_decode($result, TRUE);

        // Throw an exception if JSON decoding has errors.
        if (json_last_error() !== JSON_ERROR_NONE) {
            switch (json_last_error()) {
                case JSON_ERROR_DEPTH:
                    $jsonError = 'JSON decode error - Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $jsonError = 'JSON decode error - Underflow or the modes mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $jsonError = 'JSON decode error - Unexpected control character found';
                    break;
                case JSON_ERROR_SYNTAX:
                    $jsonError = 'JSON decode error - Syntax error, malformed JSON';
                    break;
                case JSON_ERROR_UTF8:
                    $jsonError = 'JSON decode error - Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    $jsonError = 'JSON decode error - Unknown error';
                    break;
            }
            throw new FileMakerDataApiResponseException($jsonError . ":\n" . $result, '9999');
        }

        // Throw an exception if FileMaker Data API Server has errors.
        if (isset($response['errorCode']) && $response['errorCode'] !== '0') {
            $errorMessage = "Unknown FileMaker Data API Server error.";
            if (isset($response['result'])) {
                $errorMessage = $response['result'];
            }
            throw new FileMakerDataApiResponseException($errorMessage, $response['errorCode']);
        }

        $this->_token = $response['token'];

        $this->close();
    }
};
