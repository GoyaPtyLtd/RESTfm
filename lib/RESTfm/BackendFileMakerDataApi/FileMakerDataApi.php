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

use CURLFile;
use CurlHandle;

/**
 * Represents a connection between PHP and a FileMaker Data API Server.
 */
class FileMakerDataApi {

    /**
     * FMS Data API version that we support.
     * This is the required version string as used in all backend query URLs.
     */
    const BACKEND_VERSION = 'v1';

    const DEFAULT_LIMIT = 24;
    const DEFAULT_OFFSET = 1;

    /**
     * @var CurlHandle
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
    private $_database = NULL;

    /**
     * @var string
     *  Data API session token.
     */
    private $_token = NULL;

    /**
     * @var array
     */
    private $_curlDefaultOptions = array();

    /**
     * @param string $hostspec
     *  Base URL for FM Data API Server e.g. 'https://127.0.0.1:443'
     * @param string $database
     *  Optional database name.
     * @param string $username
     *  Optional username.
     * @param string $password
     *  Optional password.
     */
    public function __construct ($hostspec, $database = NULL, $username = NULL, $password = NULL) {
        $this->_curlHandle = curl_init();
        $this->_hostspec = $hostspec;
        $this->_database = $database;
        $this->_username = $username;
        $this->_password = $password;

        // Set cURL default options.
        $this->_curlDefaultOptions = array(
            CURLOPT_USERAGENT       => 'RESTfm FileMaker Data API Backend',
            CURLOPT_FAILONERROR     => FALSE,
            CURLOPT_HEADER          => FALSE,
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_FOLLOWLOCATION  => FALSE, // Redirects don't work. Must use
                                              // https in hostspec with FMS16+
                                              // Data API.
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1, // some libcurl
                                              // versions are broken when
                                              // server supports HTTP/2
            //CURLOPT_VERBOSE         => TRUE,  // DEBUG
        );
        if (\RESTfm\Config::getVar('settings', 'strictSSLCertsFMS') === FALSE) {
            $this->_curlDefaultOptions = $this->_curlDefaultOptions +
                            array(
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                                CURLOPT_SSL_VERIFYHOST => FALSE,
                            );
        }

        if ($this->_database !== NULL) {
            // If we were provided with a database, then login and get the
            // session token.
            $this->login();
        }
    }

    public function __destruct () {
        $this->logout();

        curl_close($this->_curlHandle);
    }

    // Begin Auth

    /**
     * Logout (close) from FileMaker Data API session.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function logout () {
        if ($this->_token == NULL) {
            return;
        }

        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/sessions/' .
                                rawurlencode($this->_token),
                          'DELETE');

        // Submit the requested operation to FileMaker Data API Server.
        $response = $this->curl_exec();

        // DEBUG
        //echo "Closing response: ";
        //var_dump($response); echo "\n";
    }

    /**
     * Login (connect) to the given layout using the hostspec, database and
     * credentials provided at construction.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     * @throws \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResponseException
     *  Error from FileMaker Data API Server.
     */
    public function login () {
        if ($this->_token !== NULL) {
            // Already authenticated.
            return;
        }

        $headers = array(
            'Authorization: Basic ' .
                base64_encode($this->_username . ':' . $this->_password),
        );

        $data = json_decode('{}');

        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) . '/sessions',
                          'POST', $data, $headers);

        $result = $this->curl_exec();

        // Throw an exception if FileMaker Data API Server has errors.
        if ($result->isError()) {
            throw new FileMakerDataApiResponseException($result);
        }

        $this->_token = $result->getToken();
    }

    // Begin Metadata

    /**
     * Database Names.
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function databaseNames () {
        $headers = NULL;

        if ($this->_username !== NULL && $this->_username !== '') {
            $headers = array(
                'Authorization: Basic ' .
                    base64_encode($this->_username . ':' . $this->_password),
            );
        }

        $this->curl_setup($this->databasesUrl(), 'GET', NULL, $headers);

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    /**
     * Layout Metadata.
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function layoutMetadata ($layout) {
        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts/' .
                                rawurlencode($layout),
                          'GET');

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }


    /**
     * Layout Names.
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function layoutNames () {
        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts',
                          'GET');

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    /**
     * Script Names.
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function scriptNames () {
        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/scripts',
                          'GET');

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    // Begin Records

    /**
     * Create Record.
     *
     * @param string $layout
     * @param array $data
     *  In the form:
     *      array (
     *          'fieldName1' => 'fieldValue1',
     *          . . .
     *      )
     * @param array $params
     *  Associative array of additional FM Data API parameters
     *  e.g. script, script.presort, etc
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function createRecord ($layout, $data = array(), $params = array()) {

        $params['fieldData'] = $data;

        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts/' .
                                rawurlencode($layout) .
                                '/records' ,
                          'POST', $params);

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    /**
     * Delete Record.
     *
     * @param string $layout
     * @param string $recordId
     * @param array $params
     *  Associative array of additional FM Data API parameters
     *  e.g. script, script.presort, etc
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function deleteRecord ($layout, $recordId, $params = array()) {

        $queryString = $this->_dataToQueryString($params);
        if (!empty($queryString)) { $queryString = '?' . $queryString;}

        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts/' .
                                rawurlencode($layout) .
                                '/records/' .
                                rawurlencode($recordId) .
                                $queryString,
                          'DELETE');

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    /**
     * Edit Record.
     *
     * @param string $layout
     * @param string $recordId
     * @param array $data
     *  In the form:
     *      array (
     *          'fieldName1' => 'fieldValue1',
     *          . . .
     *      )
     * @param array $params
     *  Associative array of additional FM Data API parameters
     *  e.g. script, script.presort, etc
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function editRecord ($layout, $recordId, $data = array(), $params = array()) {

        $params['fieldData'] = $data;

        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts/' .
                                rawurlencode($layout) .
                                '/records/' .
                                rawurlencode($recordId),
                          'PATCH', $params);

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    /**
     * Get Records.
     *
     * @param string $layout
     * @param int $limit
     * @param int $offset
     * @param array $sort
     * @param array $params
     *  Associative array of additional FM Data API parameters
     *  e.g. script, script.presort, etc
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     * @throws \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResponseException
     *  Error from FileMaker Data API Server.
     */
    public function getRecords ($layout,
                                $limit = self::DEFAULT_LIMIT,
                                $offset = self::DEFAULT_OFFSET,
                                $sort = NULL,
                                $params = array()) {

        if ($offset < 1) { $offset = self::DEFAULT_OFFSET; }
        if ($limit < 1) { $limit = self::DEFAULT_LIMIT; }

        $params['_offset'] = $offset;
        $params['_limit']  = $limit;

        $queryString = $this->_dataToQueryString($params);
        if (!empty($queryString)) { $queryString = '?' . $queryString;}

        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts/' .
                                rawurlencode($layout) .
                                '/records' .
                                $queryString,
                          'GET');

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    /**
     * Get Record by RecordId
     *
     * @param string $layout
     * @param string $recordID
     * @param array $params
     *  Associative array of additional FM Data API parameters
     *  e.g. script, script.presort, etc
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     * @throws \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResponseException
     *  Error from FileMaker Data API Server.
     */
    public function getRecord ($layout, $recordID, $params = array()) {

        $queryString = $this->_dataToQueryString($params);
        if (!empty($queryString)) { $queryString = '?' . $queryString;}

        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts/' .
                                rawurlencode($layout) .
                                '/records/' .
                                rawurlencode($recordID) .
                                $queryString,
                          'GET');

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    /**
     * Find Records.
     *
     * @param string $layout
     * @param array $query
     *  Must be an array of arrays in the form:
     *      array (         # Query
     *          array (     # 1st find request
     *              '<fieldName1'> => '<searchValue1'>, # Criterion 1 (AND)
     *              '<filedName2'> => '<searchValue2'>, # Criterion 2
     *              [ 'ommit'      => 'true' ]
     *          ),
     *          array (     # 2nd find request (OR)
     *              '<fieldNameX'> => '<searchValueX'>,
     *              '<filedNameY'> => '<searchValueY'>,
     *              [ 'ommit'      => 'true' ]
     *          ),
     *          . . .
     *      )
     * @param array $params
     *  Associative array of additional FM Data API parameters
     *  e.g. script, script.presort, etc
     * @param array $sort
     *  Must be an array of arrays in the form:
     *      array (
     *          array (
     *              'fieldName' => '<fieldName1>',
     *              'sortOrder' => 'ascend|descend'
     *          ),
     *          array (
     *              'fieldName' => '<fieldName2>',
     *              'sortOrder' => 'ascend|descend'
     *          ),
     *          . . .
     *      )
     * @param int $offset
     * @param int $limit
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     * @throws \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResponseException
     *  Error from FileMaker Data API Server.
     */
    public function findRecords (
                        $layout,
                        $query = array(),
                        $params = array(),
                        $sort = array(),
                        $offset = self::DEFAULT_OFFSET,
                        $limit = self::DEFAULT_LIMIT
                    ) {

        if ($offset < 1) { $offset = self::DEFAULT_OFFSET; }
        if ($limit < 1) { $limit = self::DEFAULT_LIMIT; }

        // TEST - note double array here, converts to JSON array of objects.
        //$query = array(array(
        //    'Pcode' => '==0810',
        //));

        $params['query']  = $query;
        $params['offset'] = (string)$offset;
        $params['limit']  = (string)$limit;

        if (! empty($sort)) {
            $params['sort'] = $sort;
        }

        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts/' .
                                rawurlencode($layout) .
                                '/_find',
                          'POST', $params);

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    /**
     * @var array
     *  curl headers from last getContainerData() call.
     */
    static private $_getContainerDataHeaders = array();

    /**
     * Returns curl headers from last getContainerData() call.
     *
     * @return array
     */
    function getContainerDataHeaders () {
        return self::$_getContainerDataHeaders;
    }

    /**
     * Returns specified curl header from last getContainerData() call.
     *
     * @return string
     *  Returns NULL if not found
     */
    function getContainerDataHeader ($headerKey) {
        $found = NULL;
        foreach (self::$_getContainerDataHeaders as $header) {
            $exp = explode(':', $header, 2);
            if (count($exp) < 2) {
                continue;
            }
            list($key, $val) = $exp;
            if (strcasecmp($headerKey, $key) == 0) {
                $found = ltrim($val);
            }
        }
        return $found;
    }

    /**
     * Callback function stores curl headers for getContainerData().
     *
     * @param \CurlHandle $ch
     * @param string $header
     */
    static function _getContainerData_headerCallback ($ch, $header) {
        self::$_getContainerDataHeaders[] = $header;
        return strlen($header);
    }

    /**
     * Get container data from URL.
     *
     * @param string $url
     *  URL to fetch container data from.
     *   - Must be absolute (not relative).
     *   - Must be the same host as the dataAPI is using for auth to work.
     *
     * @return string
     *  Raw data returned from URL
     */
    function getContainerData ($url) {
        if (empty($url)) {
            return '';
        }

        // Use a clean curl handle, don't dirty up this class's private curl
        // handle used for non-container data API transactions.
        $ch = curl_init();

        self::$_getContainerDataHeaders = array();

        $options = array_replace($this->_curlDefaultOptions, array(
                CURLOPT_URL             => $url,
                CURLOPT_HTTPHEADER      => array(),
                CURLOPT_HEADERFUNCTION  => 'RESTfm\BackendFileMakerDataApi\FileMakerDataApi::_getContainerData_headerCallback',

                // We need to accept cookies and have curl follow locations
                // (redirect) for FMS > 19.3.1 Data API to work.
                CURLOPT_COOKIEFILE      => '',
                CURLOPT_FOLLOWLOCATION  => true,
                // The initial GET from the URL responds 302 and includes an
                // X-FMS-Session-Key cookie and Location: header. Curl will
                // then transparently try the Location: using that cookie. The
                // new location appears to be the same as $url with '&Redirect'
                // appended. The (unique) container data URL appears to be what
                // associates this request with a valid authorisation token,
                // making the function of the cookie unknown, and not requiring
                // the GET to set the header Authorization: bearer {token}
                // This took some time to trace and debug.
            )
        );

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);

            throw new \RESTfm\ResponseException(
                            'cURL error: ' . $curlErrno . ': ' . $curlError,
                            \RESTfm\ResponseException::INTERNALSERVERERROR);
        }

        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($responseCode !== 200) {
            throw new \RESTfm\ResponseException(
                            'container server response code: ' .
                                $responseCode . ' for URL: ' . $url,
                            \RESTfm\ResponseException::INTERNALSERVERERROR);
        }

        return($result);
    }

    /**
     * Upload to Container Field.
     *
     * @param string $layout
     * @param string $recordId
     * @param string $containerFieldName
     * @param string $data
     *  Raw binary data
     * @param string $mimeType
     *  Mime-type to set for data
     * @param string $filename
     *  Filename to set for data
     * @param array $params
     *  Associative array of additional FM Data API parameters
     *  e.g. script, script.presort, etc
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     */
    public function uploadToContainerField ($layout,
                                            $recordId,
                                            $containerFieldName,
                                            $data = NULL,
                                            $mimeType = NULL,
                                            $filename = NULL,
                                            $params = array()) {

        // TODO: look at POST $_FILES, or detect raw container data earlier,
        // and write php:// to file before tonic gets it.
        $tmpfile = tempnam(sys_get_temp_dir(), 'RESTfm_container_upload-');
        file_put_contents($tmpfile, $data);
        $curlFile = new CURLFile($tmpfile, $mimeType, $filename);

        $this->curl_setup_postFile( $this->databasesUrl() . '/' .
                                        rawurlencode($this->_database) .
                                        '/layouts/' .
                                        rawurlencode($layout) .
                                        '/records/' .
                                        rawurlencode($recordId) .
                                        '/containers/' .
                                        rawurlencode($containerFieldName),
                                    $curlFile,
                                    $params );

        try {
            $result = $this->curl_exec();
        } catch (\RESTfm\ResponseException $e) {
            // Unlink $tmpfile and rethrow
            unlink($tmpfile);
            throw $e;
        }

        unlink($tmpfile);

        // DEBUG
        //var_export($result);

        return $result;
    }

    // Begin Scripts

    /**
     * Execute script in layout.
     *
     * @param string $layout
     * @param string $scriptName
     * @param string $script_param
     *  Optional parameter to pass to script.
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL and JSON errors.
     * @throws \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResponseException
     *  Error from FileMaker Data API Server.
     */
    public function executeScript ($layout, $scriptName, $script_param = NULL) {
        $this->curl_setup($this->databasesUrl() . '/' .
                                rawurlencode($this->_database) .
                                '/layouts/' .
                                rawurlencode($layout) .
                                '/script/'.
                                rawurlencode($scriptName) .
                                '?' .
                                'script.param=' .
                                rawurlencode($script_param),
                          'GET');

        $result = $this->curl_exec();

        // DEBUG
        //var_export($result);

        return $result;
    }

    // Begin protected handlers

    /**
     * Returns the base URL for all backend 'databases' queries.
     *
     * @return string
     */
    protected function databasesUrl () {
        return('/fmi/data/' . self::BACKEND_VERSION . '/databases');
    }

    /**
     * Returns the base URL for backend 'productinfo' query.
     *
     * @return string
     */
    protected function productinfoUrl () {
        return('/fmi/data/' . self::BACKEND_VERSION . '/productinfo');
    }

    /**
     * Returns the base URL for backend 'validateSession' query.
     *
     * @return string
     */
    protected function validateSessionUrl () {
        return('/fmi/data/' . self::BACKEND_VERSION . '/validateSession');
    }

    /**
     * Setup cURL options from given parameters.
     *
     * @param string $url
     *  FM Data API URL not including hostspec.
     * @param string $method
     *  'GET', 'POST', 'PUT', 'DELETE'
     * @param array $data
     *  Optional assoc array containing data to POST/PUT. Data will be JSON
     *  encoded and Content-headers will be set automatically.
     * @param array $headers
     *  Optional array containing complete string header:
     *      array( 'X-Some-Header: 00111010101', [ ... ] )
     */
    protected function curl_setup ($url, $method, $data = NULL, $headers = NULL) {

        $options = array_replace($this->_curlDefaultOptions, array(
                CURLOPT_URL             => $this->_hostspec . $url,
                CURLOPT_CUSTOMREQUEST   => $method,
            )
        );

        if ($headers === NULL) {
            $headers = array();
        }

        if ($this->_token !== NULL) {
            $headers[] = 'Authorization: Bearer ' . $this->_token;
        }

        if ($data !== NULL) {
            $jsonData = json_encode($data);
            $headers[] = 'Content-Length: ' . strlen($jsonData);

            // As of FMSv18, the data API is no longer able to accept UTF-8
            // charset.
            // 20190624 - Gavin Stewart, Goya Pty Ltd.
            //$headers[] = 'Content-Type: application/json; charset=UTF-8';
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = $jsonData;
        }

        if (count($headers) > 0) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        // DEBUG
        //echo "cURL options: ";
        //var_export($options);
        //echo "\n";
        //if ($data !== NULL) {
        //    echo "cURL jsonData: " . $jsonData . "\n";
        //}

        curl_setopt_array($this->_curlHandle, $options);
    }

    /**
     * Setup cURL options from given parameters.
     *
     * @param string $url
     *  FM Data API URL not including hostspec.
     * @param CURLFile $curlFile
     * @param array $headers
     *  Optional array containing complete string header:
     *      array( 'X-Some-Header: 00111010101', [ ... ] )
     */
    protected function curl_setup_postFile ($url, CURLFile $curlFile, $headers = NULL) {

        $options = array_replace($this->_curlDefaultOptions, array(
                CURLOPT_URL             => $this->_hostspec . $url,
                CURLOPT_CUSTOMREQUEST   => 'POST',
            )
        );

        if ($headers === NULL) {
            $headers = array();
        }

        if ($this->_token !== NULL) {
            $headers[] = 'Authorization: Bearer ' . $this->_token;
        }

        $args['upload'] = $curlFile;
        $options[CURLOPT_POSTFIELDS] = $args;

        if (count($headers) > 0) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        // DEBUG
        //echo "cURL options: ";
        //var_export($options);
        //echo "\n";

        curl_setopt_array($this->_curlHandle, $options);
    }

    /**
     * Perform a cURL session on private curl handle, throwing an exception
     * on error.
     *
     * @return \RESTfm\BackendFileMakerDataApi\FileMakerDataApiResult
     *  Object containing decoded JSON response from FileMaker Data API Server.
     *
     * @throws \RESTfm\ResponseException
     *  On cURL error.
     */
    protected function curl_exec () {
        $ch = $this->_curlHandle;

        // Submit the requested operation to FileMaker Data API Server.
        $result = \curl_exec($ch);

        // Throw an exception if cURL has errors.
        if(curl_errno($ch)) {
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);

            throw new \RESTfm\ResponseException(
                            'cURL error: ' . $curlErrno . ': ' . $curlError,
                            \RESTfm\ResponseException::INTERNALSERVERERROR);
        }

        // DEBUG
        //echo "cURL raw result: " . $result . "\n";

        return new FileMakerDataApiResult($this->json_decode($result, TRUE));
    }

    /**
     * Returns an encoded query string from the provided array of data.
     *
     * @param array $data
     *
     * @return string
     *  An encoded query string
     *  OR
     *  An empty string if data array is empty.
     */
    protected function _dataToQueryString (array $data) {
        $queryString = '';
        if (!empty($data)) {
            $queryString = http_build_query($data, '', ini_get('arg_separator.output'), PHP_QUERY_RFC3986);
        }
        return $queryString;
    }


    /**
     * Returns TRUE if response contains a FileMaker Data API Server error.
     *
     * @param array $response
     *  Response array decoded from FileMaker Data API Server JSON.
     *
     * @return bool
     *  TRUE on non zero 'errorCode',
     *  TRUE on presence of 'errorMessage',
     *  else FALSE.
     *
     * @throws \RESTfm\ResponseException
     *  If response does not contain a key named 'errorCode'. i.e. invlaid
     */
    /*
    protected function isError ($response) {
        if ( isset($response['messages']) &&
                isset($response['messages'][0]) &&
                isset($response['messages'][0]['code']) ) {
            if ($response['messages'][0]['code'] !== '0') {
                return TRUE;
            }
        } else {
            // Invalid response.
            error_log('RESTfm FileMakerDataApi::isError() invalid: ' . serialize($response));
            throw new \RESTfm\ResponseException(
                            'Invalid response from FMDataAPI Server',
                            \RESTfm\ResponseException::INTERNALSERVERERROR);
        }
        return FALSE;
    }
    */

    /**
     * Decodes a JSON string, throws an exception if decoding has errors.
     *
     * @param string $json
     * @param bool $assoc
     * @param int $depth
     * @param int $options
     *
     * @return assoc|object Returns the value encoded in json in appropriate PHP type.
     *
     * @throws \RESTfm\ResponseException
     *  If there is an error decoding JSON.
     */
    protected function json_decode ($json, $assoc = FALSE, $depth = 512, $options = 0) {
        $value = \json_decode($json, $assoc, $depth, $options);

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
            error_log('RESTfm FileMakerDataApi::json_decode() error: ' . $jsonError . ":\n" . $json);
            throw new \RESTfm\ResponseException($jsonError,
                            \RESTfm\ResponseException::INTERNALSERVERERROR);
        }

        return $value;
    }
};
