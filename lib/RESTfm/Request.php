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
 * RESTfm\Request class.
 *
 * Extends Tonic's Request class for RESTfm specific features.
 */
class Request extends \Tonic\Request {

    /**
     * @var \RESTfm\Message\Message
     *  Parsed HTTP request data.
     */
    protected $_Message = NULL;

    /*
     * @var \RESTfm\Parameters
     *  Determined parameters for this Request.
     */
    protected $_Parameters = NULL;

    /**
     * @var \RESTfm\Credentials
     *  Determined credentials for this Request.
     */
    protected $_Credentials = NULL;

    /**
     * @var string
     *  Format string as determined by request and available formats.
     */
    protected $_format;

    /**
     * @var array
     *  Associative array of RFM* parameters from query string.
     */
    protected $_parametersQueryString = array();

    /**
     * @var array
     *  Associative array of RFM* parameters from HTML POST body.
     */
    protected $_parametersPost = array();

    /**
     * @var array
     *  Associative array of RFM* parameters from 'info' section of data.
     */
    protected $_parametersData = array();

    /**
     * @var array
     *  Map of generic method names to HTTP method names.
     */
    protected $_genericMethodNames = array (
        'CREATE' => 'POST',
        'READ' => 'GET',
        'UPDATE' => 'PUT',
        'DELETE' => 'DELETE',
    );

    /**
     * Instantiate the resource class that matches the request URI the best.
     *
     * Override superclass method to return \Tonic\ResponseException as
     * \RESTfm\ResponseException.
     *
     * @return Resource
     *
     * @throws \RESTfm\ResponseException
     *  404 (Not Found) if the resource does not exist.
     */
    public function loadResource () {
        // Call parent class method.
        try {
            return(parent::loadResource());
        } catch (\Tonic\ResponseException $e) {
            throw new \RESTfm\ResponseException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Parse all information available to this request, including query
     * string parameters and HTTP message body. The body may contain further
     * parameters and formatted data. Credentials will be determined here.
     *
     * Notes on data encoding and formats:
     *  - POST (and only POST) allows for HTTP-encoded bodies:
     *      - multipart/form-data (useful for uploading binary data).
     *      - application/x-www-form-urlencoded (same encoding as query string).
     *
     *    These encodings may contain RFM* parameters.
     *
     *    The RFMdata parameter may be used to embed a rawurlencoded (see
     *    RFC 3986) document in a RESTfm accepted format.
     *
     *    A RFMformat parameter must be present if RFMdata exists, and
     *    must specify a RESTfm accepted format as listed in RESTfm.ini
     *    The RFMformat parameter may be used alone to override HTTP
     *    Content-type headers for POST data.
     *
     *  - PUT and POST may send pure non-HTTP-encoded data in body. (Technically
     *    so can GET and DELETE, but this may not be supported in all cases by
     *    all servers and clients).
     *
     *    Submitted data should be transmitted with the following headers at
     *    minimum:
     *       Content-Type: application/octet-stream
     *       Content-Length: nnn
     *
     *    Other content types are acceptable depending on format:
     *       Content-Type: application/json
     *       Content-Type: application/xml
     *       Content-Type: text/xml
     *       Content-Type: text/plain
     *
     *  - GET may have data in the query string that is handled automatically
     *    as POST application/x-www-form-urlencoded.
     *
     * @throws ResponseException
     *  400 (Bad Request) if data is present but format is unknown.
     */
    public function parse () {

        $this->_Message = new Message\Message();

        $this->_Parameters = new Parameters();

        $this->_handleGetData();

        $this->_handlePostData();

        // Set requested override format from parameters received
        // so far. This allows mismatched request and response formats.
        if (isset($this->_parametersPost['RFMformat'])) {
            $this->_format = $this->_parametersPost['RFMformat'];
        }
        if (isset($this->_parametersQueryString['RFMformat'])) {
            $this->_format = $this->_parametersQueryString['RFMformat'];
        }

        $this->_parseFormattedData();

        $this->_setParameters();

        // Set RFMurl encoding format.
        if (isset($this->_Parameters->RFMfixFM01)) {
            Url::setEncoding(Url::RFMfixFM01);
        }
        if (isset($this->_Parameters->RFMfixFM02)) {
            Url::setEncoding(Url::RFMfixFM02);
        }

        // Set requested override method.
        if (isset($this->_Parameters->RFMmethod)) {
            $this->method = strtoupper($this->_Parameters->RFMmethod);
            if (isset($this->_genericMethodNames[$this->method])) {
                $this->method = $this->_genericMethodNames[$this->method];
            }
        }

        $this->_Credentials = new Credentials($this->_Parameters);
    }

    /**
     * Returns the raw data string.
     *
     * @return string
     */
    public function getData () {
        return $this->data;
    }

    /**
     * Returns the \RESTfm\Message\Message object populated from the HTTP request data.
     *
     * @return \RESTfm\Message\Message
     */
    public function getMessage () {
        return $this->_Message;
    }

    /**
     * Returns the Parameters object with parameters for this request.
     *
     * @return \RESTfm\Parameters
     */
    public function getParameters () {
        return $this->_Parameters;
    }

    /**
     * Returns the Credentials object with credentials for this request.
     *
     * @return \RESTfm\Credentials
     */
    public function getCredentials () {
        return $this->_Credentials;
    }

    /**
     * Returns the determined format of this request.
     *
     * @return string
     */
    public function getFormat () {
        return $this->_format;
    }

    /**
     * Extract encoded FieldName/Value data from query string if this request
     * was called using the HTTP GET method.
     *
     * It makes no sense to extract field data from the query string of non-GET
     * calls.
     *
     * RFM* parameters from the query string are also determined here.
     */
    protected function _handleGetData () {
        $queryString = new RFMfixQueryString(TRUE);

        // Identify RFM* query string parameters.
        $this->_parametersQueryString = $queryString->getRegex('/^RFM.*/');

        // Return immediately unless we are called with GET.
        if (strtoupper($this->method != 'GET')) {
            return;
        }

        // Handle data.
        if (isset($queryString->RFMdata)) {
            // Allow document embedded in RFMdata parameter. Store for
            // later format parsing.
            $this->data = $queryString->RFMdata;
            unset($this->_parametersQueryString['RFMdata']);
        } else {
            // All submitted data is in query string, we will populate
            // \RESTfm\Message\Message ourselves.
            $getData = $queryString->getRegex('/^(?!RFM).+/'); // NOT RFM*
            if (count($getData) > 0) {
                $this->_Message->addRecord(new Message\Record(NULL, NULL, $getData));
            }
        }
    }

    /**
     * Extract submitted data from body if this request was called
     * with the HTTP POST method, AND the Content-Type is
     * multipart/form-data OR application/x-www-form-urlencoded.
     *
     * RFM* parameters from the POST form data are also determined here.
     */
    protected function _handlePostData () {

        // Return immediately unless we are called with POST.
        if (strtoupper($this->method) != 'POST') {
            return;
        }

        // Find POST form data.
        $postData = array();
        if (isset($this->_parametersQueryString['RFMformat'])) {
            // This format is already explicitly defined, do nothing and leave
            // this for the format parsers.
            return;
        } elseif (stripos($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') !== FALSE) {
            // Use our parser, not PHP's.
            $parser = new RFMfixQueryString();
            $parser->parse_str($this->data, $postData);
        } elseif (stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== FALSE ) {
            // At the moment we are at the mercy of PHP's parsing. This
            // _will_ damage anything with dots and spaces, by mangling into
            // underscores. (This was a part of the old legacy support for
            // globals in PHP).
            $postData = $_POST;
        } else {
            // Not form data, so we leave this for the format parsers.
            return;
        }

        // Handle extra encoding for FileMaker 13 httppost: (Get From URL)
        // RFMfixFM02 parameter may be in POST ($postData), or in the already
        // parsed query string.
        if (isset($postData['RFMfixFM02']) ||
                isset($this->_parametersQueryString['RFMfixFM02'])) {
            $decodedData = array();
            foreach ($postData as $key => $value) {
                $decodedData[RFMfixFM02::postDecode($key)] =
                        RFMfixFM02::postDecode($value);
            }
            $postData = $decodedData;
        }

        // Identify and store RFM* parameters then remove from $postData.
        $toDelete = array();
        foreach ($postData as $key => $value) {
            if (preg_match('/^RFM*/', $key)) {
                if ('RFMdata' == $key) {
                    continue;
                }
                $this->_parametersPost[$key] = $value;
                $toDelete[] = $key;
            }
        }
        foreach ($toDelete as $key) {
            unset($postData[$key]);
        }

        // Handle postData.
        if (isset($postData['RFMdata'])) {
            // Allow document embedded in RFMdata parameter. Store for
            // later format parsing.
            $this->data = $postData['RFMdata'];
        } else {
            // All submitted data is in array, we will populate
            // \RESTfm\Message\Message ourselves.
            $this->_Message->addRecord(new Message\Record(NULL, NULL, $postData));
            unset($this->data);
        }
    }

    /**
     * Parse data based on the determined format.
     *
     * RFM* parameters from the 'info' section are also determined here.
     */
    protected function _parseFormattedData () {

        // Return immediately if there is no data to parse.
        if (empty($this->data)) {
            return;
        }

        // Handle raw container data.
        if (isset($this->_parametersQueryString['RFMcontainer']) &&
                strtoupper($this->_parametersQueryString['RFMcontainer']) == 'RAW') {
            if (! isset($this->_format)) {
                // Pass through Content-Type as format for container data
                $this->_format = $_SERVER['CONTENT_TYPE'];
            }
            // No parsable data, just return.
            return;
        }

        // If a format hasn't been determined yet, work one out.
        if (! isset($this->_format)) {
            if (isset($this->_parametersQueryString['RFMurlencoded'])) {
                // Override uploaded data format.
                $this->_format = 'application/x-www-form-urlencoded';
            } else {
                // Determine the most acceptable format using tonic.
                $this->_format = $this->mostAcceptable(Config::getFormats());
            }
        }

        // Ensure we have a format.
        if ($this->_format == '') {
            // This is trouble, we have data but in no known format.
            throw new ResponseException(
                        'Unable to determine format for resource ' . $this->uri,
                        Response::BADREQUEST);
        }

        // Check if our format is available through a provided xslt.
        if (file_exists('lib/xslt/'.$this->_format.'_import.xslt')) {
            $useXSLT = 'lib/xslt/'.$this->_format.'_import.xslt';
            $xsltFile = file_get_contents($useXSLT);
            $xsltProcessor = new \XSLTProcessor();
            $xsltProcessor->importStyleSheet(new \SimpleXMLElement($xsltFile));
            $this->data = $xsltProcessor->transformToXml(new \SimpleXMLElement($this->data));

            $this->_format = 'xml';
        }

        // Parse submitted data through formatter.
        $dataFormatter = \RESTfm\FormatFactory::makeFormatter($this->_format);
        $dataFormatter->parse($this->_Message, $this->data);

        // Identify RFM* parameters in 'info' section, store as request
        // parameter, finally remove from 'info' section.
        $toDelete = array();
        foreach ($this->_Message->getInfos() as $key => $val) {
            if (preg_match('/^RFM/', $key)) {
                $this->_parametersData[$key] = $val;
                $toDelete[] = $key;
            }
        }
        foreach ($toDelete as $key) {
            $this->_Message->unsetInfo($key);
        }
    }

    /**
     * Populate parameters by mergeing identified parameters from various
     * sources.
     */
    protected function _setParameters () {
        // Merge in increasing order of priority. i.e. later parameters
        // of the same name will override earlier entries.
        $this->_Parameters->merge($this->_parametersData);
        $this->_Parameters->merge($this->_parametersPost);
        $this->_Parameters->merge($this->_parametersQueryString);
    }

};
