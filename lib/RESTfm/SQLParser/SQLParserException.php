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

class SQLParserException extends Exception {
    /* ParserSQL Exception codes.*/

    // Lexer errors.
    const   EX_LEXER                = 1;

    // Parser errors.
    const   EX_PARSER               = 2;
}
