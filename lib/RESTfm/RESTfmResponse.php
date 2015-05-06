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

require_once 'RESTfmConfig.php';
require_once 'RESTfmQueryString.php';
require_once 'FormatFactory.php';
require_once 'RESTfmDataSimple.php';

/**
 * RESTfmResponse class.
 */
class RESTfmResponse extends Response {

    /**
     * @var string
     *  Format string as determined by request and available formats.
     */
    public $format = 'html';

    /**
     * @var string
     *  Translation between common formats and their content types.
     */
    public $contentTypes = array (
        'json'  =>    'application/json',
        'html'  =>    'text/html',
        'text'  =>    'text/plain',
        'txt'   =>    'text/plain',
        'xml'   =>    'text/xml; charset=UTF-8',
    );

    /**
     * @var array
     *  Mapping between common status code and reason phrases.
     */
    public $codeReason = array (
        Response::OK                => 'OK',
        Response::CREATED           => 'Created',
        Response::NOCONTENT         => 'No Content',
        Response::MOVEDPERMANENTLY  => 'Moved Permanently',
        Response::FOUND             => 'Found',
        Response::SEEOTHER          => 'See Other',
        Response::NOTMODIFIED       => 'Not Modified',
        Response::TEMPORARYREDIRECT => 'Temporary Redirect',
        Response::BADREQUEST        => 'Bad Request',
        Response::UNAUTHORIZED      => 'Unauthorized',
        Response::FORBIDDEN         => 'Forbidden',
        Response::NOTFOUND          => 'Not Found',
        Response::METHODNOTALLOWED  => 'Method Not Allowed',
        Response::NOTACCEPTABLE     => 'Not Acceptable',
        Response::CONFLICT          => 'Conflict',
        Response::GONE              => 'Gone',
        Response::LENGTHREQUIRED    => 'Length Required',
        Response::PRECONDITIONFAILED => 'Precondition Failed',
        Response::UNSUPPORTEDMEDIATYPE => 'Unsupported Media Type',
        Response::INTERNALSERVERERROR => 'Internal Server Error',
    );

    /**
     * Override superclass constructor.
     *
     * @param RESTfmRequest $request
     *  The request object generating this response.
     * @param string $uri
     *  The URL of the actual resource being used to build the response.
     */
    public function __construct($request, $uri = NULL) {
        parent::__construct($request, $uri);
        $this->format = $request->mostAcceptable(RESTfmConfig::getFormats());

        // If we have an RFMreauth query string, then we need to force a
        // change of authorisation credentials. We only expect this when
        // using the html format.
        $queryString = new RESTfmQueryString(TRUE);
        if(isset($queryString->RFMreauth)) {
            $currentUsername = $request->getRESTfmCredentials()->getUsername();
            // Only send unauthorised if username hasn't been set to something
            // different yet.
            // This is peculiar to the html format, we needed a way to elicit
            // a browser authentication dialogue via a simple href link. The
            // query string does not change, we can only detect that a
            // different username has been entered.
            if ($currentUsername == urldecode($queryString->RFMreauth) && $queryString->RFMreauth != '') {
                header('Refresh:0;url=' . RESTfmConfig::getVar('settings', 'baseURI'));
                throw new ResponseException("User requested re-authorisation.", Response::UNAUTHORIZED);
            }

            // Remove RFMreauth from server querystring.
            unset($queryString->RFMreauth);
            $queryString->toServer();
        }
    }

    /**
     * Convert the format parameter into a content type string.
     *
     * @param string $format
     */
    public function contentType($format) {
        if (isset($this->contentTypes[$format])) {
            return $this->contentTypes[$format];
        }

        // Default fallback.
        return $this->contentTypes['text'];
    }

    /**
     * Output the response.
     *
     * Overrides tonic's method.
     */
    public function output() {
        $this->addHeader('X-RESTfm-Version', Version::getVersion());
        $this->addHeader('X-RESTfm-Protocol', Version::getProtocol());
        $this->addHeader('X-RESTfm-Status', $this->code);
        $this->addHeader('X-RESTfm-Reason', $this->reason);
        $this->addHeader('X-RESTfm-Method', $this->request->method);

        // Check if we need to authorise this origin (CORS)
        $configOrigins = RESTfmConfig::getVar('allowed_origins');
        if (isset($_SERVER["HTTP_ORIGIN"]) && is_array($configOrigins)) {
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

        // Ensure we have response data!
        if ($this->_restfmData == NULL) {
            $this->_restfmData = new RESTfmData();
        }

        // Inject X-RESTfm headers into 'info' section.
        foreach ($this->headers as $header => $value) {
            if (preg_match('/^X-RESTfm-/i', $header)) {
                $this->_restfmData->setSectionData('info', $header, $value);
            }
        }

        // Inject additional info into 'info' section.
        foreach ($this->_info as $name => $value) {
            $this->_restfmData->setSectionData('info', $name, $value);
        }

        // Build the message body of this response.
        $this->_buildMessage();

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
     * Store response data.
     *
     * @param RESTfmDataAbstract $restfmData
     */
    public function setData(RESTfmDataAbstract $restfmData) {
        $this->_restfmData = $restfmData;
    }

    /**
     * Set the response status code and reason.
     *
     * @param integer $statusCode
     * @param string $statusReason
     */
    public function setStatus($statusCode = 0, $statusReason = '') {
        $this->code = $statusCode;

        if ($statusReason == '') {
            if (isset($this->codeReason[$statusCode])) {
                $this->reason = $this->codeReason[$statusCode];
            }
        } else {
            $this->reason = $statusReason;
        }
    }

    /**
     * Add content for inclusion in 'info' section of response.
     *
     * @var string $name
     * @var string $value
     */
    public function addInfo ($name, $value) {
        $this->_info[$name] = $value;
    }

    // --- Protected --- ///

    /**
     * @var string
     *  Status code's corresponding reason.
     */
    protected $reason = '';

    /**
     * @var RESTfmDataAbstract
     *  The data associated with this response.
     */
    protected $_restfmData = NULL;

    /**
     * @var array
     *  Associative array of additional 'info' section content.
     */
    protected $_info = array();

    /**
     * Build the headers and message body from the stored data and the
     * determined format.
     */
    protected function _buildMessage() {

        $formatAs = $this->format;

        // Check if our format is available through a provided xslt.
        $useXSLT = NULL;
        if (file_exists('lib/xslt/'.$this->format.'_export.xslt')) {
            $useXSLT = 'lib/xslt/'.$this->format.'_export.xslt';
            $formatAs = 'xml';
        }

        // Build a formatter. We fall back to text, because we also format
        // errors via RESTfmResponseException. No output would be bad!
        try {
            $formatter = FormatFactory::makeFormatter($formatAs);
        } catch (Exception $e) {
            // Fallback
            $formatAs = 'txt';
            try {
                $formatter = FormatFactory::makeFormatter($formatAs);
            } catch (Exception $e) {
                // Fatal error, we should never get here.
                $this->code = $e->getCode();
                $this->reason = $e->getMessage();
                $this->body = 'Error ' . $this->code . ': ' . $this->reason;
                return;
            }
        }

        // Special case for html format. Needs to be able to display the
        // username for the browser UI.
        if ($formatAs == 'html') {
            $formatter->setUsername(
                    $this->request->getRESTfmCredentials()->getUsername() );
        }

        $this->addHeader('Content-type', $this->contentType($formatAs));
        $this->body = $formatter->write($this->_restfmData);

        // Use XSLT to produce final format.
        if (isset($useXSLT)) {
            $xsltFile = file_get_contents($useXSLT);
            $xsltProcessor = new XSLTProcessor();
            $xsltXML = new SimpleXMLElement($xsltFile);
            $outputMethod = $xsltXML->xpath('xsl:output/@method');
            $this->addHeader('Content-type', $this->contentType((string)$outputMethod[0]));
            $xsltProcessor->importStyleSheet($xsltXML);
            $this->body = $xsltProcessor->transformToXml(new SimpleXMLElement($this->body));
        }
    }

}
