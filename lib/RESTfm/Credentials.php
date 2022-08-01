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
 * RESTfm credentials class identifies and holds authentication/authorisation
 * credentials for request.
 */
class Credentials {

    /**
     * @var string
     */
    private $_username = '';

    /**
     * @var string
     */
    private $_password = '';

    /**
     * Work out credentials from request parameters and config.
     *
     * @param Parameters $parameters
     *  Parameters for this request.
     */
    public function __construct (Parameters $parameters) {

        $username = '';
        $password = '';

        // Check for default authentication fall-back
        if (Config::getVar('database', 'useDefaultAuthentication') === TRUE) {
            $username = Config::getVar('database', 'defaultUsername');
            $password = Config::getVar('database', 'defaultPassword');
        }

        // Check for API key in parameters
        $RFMkey = $parameters->RFMkey;
        if (isset($RFMkey) && Config::checkVar('keys', $RFMkey)) {
            list($username, $password) = explode(':', Config::getVar('keys', $RFMkey), 2);
        }

        // Work around for HTTP Basic Auth for Apache CGI/FCGI/suExec server modes.
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Basic\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            list($n, $p) = explode(':', base64_decode($matches[1]));
            $_SERVER['PHP_AUTH_USER'] = strip_tags($n);
            $_SERVER['PHP_AUTH_PW'] = strip_tags($p);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && preg_match('/Basic\s+(.*)$/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $matches)) {
            list($n, $p) = explode(':', base64_decode($matches[1]));
            $_SERVER['PHP_AUTH_USER'] = strip_tags($n);
            $_SERVER['PHP_AUTH_PW'] = strip_tags($p);
        }

        // Check for API key or username/password in HTTP basic authentication.
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $RFMkey = $_SERVER['PHP_AUTH_USER'];
            if (isset($RFMkey) && Config::checkVar('keys', $RFMkey)) {
                list($username, $password) = explode(':', Config::getVar('keys', $RFMkey), 2);
            } else {
                $username = $_SERVER['PHP_AUTH_USER'];
                if (isset($_SERVER['PHP_AUTH_PW'])) {
                    $password = $_SERVER['PHP_AUTH_PW'];
                }
            }
        }

        // If we set nothing, this is the equivalent to "guest" access.
        if (!empty($username)) {
            $this->_username = $username;
        }
        if (!empty($password)) {
            $this->_password = $password;
        }
    }

    /**
     * Returns determined username.
     *
     * @return string
     */
    public function getUsername () {
        return $this->_username;
    }

    /**
     * Returns determined password.
     *
     * @return string
     */
    public function getPassword () {
        return $this->_password;
    }

};
