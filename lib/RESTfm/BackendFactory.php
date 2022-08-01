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
 * (Database) Backend Factory - instantiates appropriate database backend.
 */
class BackendFactory {

    /*
     * Possible backend types.
     *
     * Each string must be the directory name of the backend (minus ^Backend).
     */
    const   BACKEND_FILEMAKERPHPAPI     = "FileMaker",
            BACKEND_FILEMAKERDATAAPI    = "FileMakerDataApi",
            BACKEND_PDO                 = "Pdo";

    /**
     * Instantiate and return the appropriate backend object.
     *
     * @param Request $request
     *  Originating request containing credentials for backend authentication.
     *
     * @param string $database
     *  Database name.
     *
     * @throws ResponseException
     *  When no appropriate backend found.
     *
     * @return BackendAbstract
     */
    public static function make (Request $request, $database = NULL) {
        // Determine which FileMaker backend API is in use.
        if (Config::getVar('database', 'dataApi') === TRUE) {
            $type = self::BACKEND_FILEMAKERDATAAPI;
        } else {
            $type = self::BACKEND_FILEMAKERPHPAPI;
        }

        // $database may map to a PDO backend.
        if ($database !== NULL) {
            if (Config::checkVar('databasePDOMap', $database)) {
                $type = self::BACKEND_PDO;
            }
        }

        $backendClassName = 'RESTfm\\Backend' . $type . '\\' . 'Backend';

        $restfmCredentials = $request->getCredentials();

        switch ($type) {
            case self::BACKEND_FILEMAKERDATAAPI:
                $backendObject = new $backendClassName(
                            array(
                                'hostspec' => Config::getVar('database', 'hostspec'),
                                'database' => $database,
                            ),
                            $restfmCredentials->getUsername(),
                            $restfmCredentials->getPassword()
                        );
                break;
            case self::BACKEND_PDO:
                $backendObject = new $backendClassName(
                            Config::getVar('databasePDOMap', $database),
                            $restfmCredentials->getUsername(),
                            $restfmCredentials->getPassword()
                        );
                break;
            default:
                $backendObject = new $backendClassName(
                            Config::getVar('database', 'hostspec'),
                            $restfmCredentials->getUsername(),
                            $restfmCredentials->getPassword()
                        );
                break;
        }

        return $backendObject;
    }

};
