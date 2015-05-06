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
require_once 'RESTfmRequest.php';
require_once 'RESTfmCredentials.php';
require_once 'BackendAbstract.php';

/**
 * (Database) Backend Factory - instantiates appropriate database backend.
 */
class BackendFactory {

    /*
     * Possible backend types.
     */
    const   BACKEND_FILEMAKER = "FileMaker",
            BACKEND_PDO = "Pdo";

    /**
     * Instantiate and return the appropriate backend object.
     *
     * @param RESTfmRequest $request
     *  Originating request containing credentials for backend authentication.
     *
     * @param string $database
     *  Database name.
     *
     * @throws RESTfmResponseException
     *  When no appropriate backend found.
     *
     * @return BackendAbstract
     */
    public static function make (RESTfmRequest $request, $database = NULL) {
        // FileMaker is the default, but $database may map to a PDO backend.
        $type = self::BACKEND_FILEMAKER;
        if ($database !== NULL && RESTfmConfig::checkVar('databasePDOMap', $database)) {
            $type = self::BACKEND_PDO;
        }

        // Identify backend class.
        $backendName = 'Backend' . $type;
        $classPath = dirname(__FILE__) . DIRECTORY_SEPARATOR .
                     $backendName . DIRECTORY_SEPARATOR .
                     $backendName . '.php';
        if (!file_exists($classPath)) {
            throw new RESTfmResponseException('Unknown backend: ' . $type, 500);
        }
        require_once($classPath);

        $restfmCredentials = $request->getRESTfmCredentials();

        if ($type === self::BACKEND_PDO) {
            $backendObject = new $backendName(
                            RESTfmConfig::getVar('databasePDOMap', $database),
                            $restfmCredentials->getUsername(),
                            $restfmCredentials->getPassword()
                        );
        } else {    # Default to FileMaker
            $backendObject = new $backendName(
                            RESTfmConfig::getVar('database', 'hostspec'),
                            $restfmCredentials->getUsername(),
                            $restfmCredentials->getPassword()
                        );
        }

        return $backendObject;
    }

};
