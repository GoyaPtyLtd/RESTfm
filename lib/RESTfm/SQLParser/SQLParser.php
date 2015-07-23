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

 /*
  * References:
  *   EBNF, grammar, left-recursion and precedence:
  *     - https://en.wikipedia.org/wiki/Extended_Backus%E2%80%93Naur_Form
  *     - http://stackoverflow.com/questions/7422298/converting-bnf-to-ebnf-parentheses-without-recursion
  *     - http://stackoverflow.com/questions/4164842/how-would-i-implement-parsing-using-operator-precedence
  *     - http://eli.thegreenplace.net/2009/03/14/some-problems-of-recursive-descent-parsers
  *
  *   Lexical analysis and tokenisation:
  *     - https://en.wikipedia.org/wiki/Token_%28parser%29
  *     - https://en.wikipedia.org/wiki/Lexical_grammar
  *
  *   Recursive Descent Parser:
  *     - https://en.wikipedia.org/wiki/Recursive_descent_parser
  */

 /*

Example complex query:
 SELECT Locality, "First Name",'Order in search' WHERE (Locality="New York" AND Zip<3000) OR ('First Name' LIKE John* and Age <= 30) OMIT Points=0 ORDER BY Age DESC , "Points" ASC LIMIT 10 OFFSET 2

We are aiming to support all of FileMaker's available find logic, the majority
is via passthrough of substitution and operator characters that are part of the
field value string (we use the SQL 'LIKE' operator to designate these):
http://www.filemaker.com/help/html/find_sort.5.4.html

SQL-like syntax grammar in EBNF-like (using regexes) form, without left
recursion for use in a Recursive Descent Parser:

sql-like_query:     select? where? order_by? limit? offset?

select:             'SELECT' string ( ',' string )*

where:              'WHERE' ( 'OMIT' )? where_condition

where_condition :   where_and ( 'OR' | 'OMIT' where_and )*

where_and:          where_term ( 'AND' where_term )*

where_term:         '(' where_condition ')' |
                    string ( '=' | '<' | '>' | '<=' | '>=' | 'LIKE' ) string

order_by:           'ORDER BY' string ( 'ASC' | 'DESC' )? ( ',' string ( 'ASC' | 'DESC' )? )*

limit:              'LIMIT' string

offset:             'OFFSET' string

 */

require_once('SQLParserException.php');
require_once('SQLToken.php');
require_once('SQLLexer.php');

/**
 * SQLParser Class.
 *
 * This is a Recursive Descent Parser. Calling parse() will ensure the
 * syntax of the $sqlQuery constructor parameter is valid.
 *
 * This class should be extended, and the Recursive Descent Parser methods
 * overridden to make use of the parsed data. Care must be taken to preserve
 * the logic.
 */
class SQLParser {

    // --- Public Methods --- //

    /**
     * Constructor.
     *
     * @param string $sqlQuery
     *  String containing SQL-like syntax to parse.
     *
     * @return ParserSQL
     */
    public function __construct ($sqlQuery) {
        $this->_input = $sqlQuery;
    }

    /**
     * Parse input string provided on construction.
     *
     * @throws SQLParserException
     *  On error.
     */
    public function parse () {
        $this->_lexer = new SQLLexer($this->_input);
        $this->sql_like_query();
    }

    /**
     * Set debugging output.
     *
     * @param boolean $enable
     *  Set TRUE to enable debugging output.
     */
    public function setDebug ($enable = TRUE) {
        $this->_debug = $enable;
    }


    // --- Protected Members --- //

    /**
     * @var string
     *  Input string for parsing.
     */
    protected $_input;

    /**
     * @var boolean
     *  Set true to enable debug output.
     */
    protected $_debug = FALSE;

    /**
     * @var SQLLexer
     *  The currently running lexer.
     */
    protected $_lexer;

    /**
     * @var SQLToken
     *  The current token that the parser is working on.
     */
    protected $_token;

    /**
     * Set $this->_token to the next available token from $this->_lexer
     */
    protected function nextToken () {
       $this->_token = $this->_lexer->getToken();
       if ($this->_debug) { echo $this->_token . "\n"; }
    }

    /**
     * Handle parser error messages.
     *
     * @param string $message
     *  Error message.
     *
     * @throws SQLParserException
     *  When called.
     */
    protected function error ($message) {
        $fullError = '';
        if ($this->_token->isType(SQLToken::TOK_NULL)) {
            $fullError = $message . '. No more symbols.';
        } else {
            $fullError = $message . ' at position ' .
                        ($this->_token->pos) . ': '.
                        $this->_token->value;
        }

        // Debugging
        //debug_print_backtrace();
        //echo $fullError . "\n";
        //exit;

        throw new SQLParserException(
            $fullError,
            SQLParserException::EX_PARSER
        );
    }

    /**
     * Consume token only if it is of the provided $tokenType.
     *
     * @var Integer $tokenType
     *  Token type as specified in SQLToken::TOK_* constants.
     *
     * @return boolean
     *  Returns TRUE on matching accepted token type, else FALSE.
     */
    protected function accept ($tokenType) {
        if ($this->_token->isType($tokenType)) {
            $this->nextToken();
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Same as accept() but returns the accepted token's value rather
     * than returning TRUE. Still returns === FALSE if not accepted.
     *
     * @var Integer $tokenType
     *  Token type as specified in SQLToken::TOK_* constants.
     *
     * @return boolean
     *  Returns token's value on matching accepted token type, else FALSE.
     */
    protected function acceptGetValue ($tokenType) {
        if ($this->_token->isType($tokenType)) {
            $ret = $this->_token->value;
            $this->nextToken();
            return $ret;
        }
        return FALSE;
    }

    /**
     * Enforce token is of the provided $tokenType, consuming it, otherwise
     * raise an error.
     *
     * @var integer $tokenType
     *  Token type as specified in SQLToken::TOK_* constants.
     *
     * @return boolean
     *  Returns TRUE on matching expected token type.
     */
    protected function expect ($tokenType) {
        if ($this->accept($tokenType)) {
            return TRUE;
        }
        $this->error('Unexpected symbol. Expected ' .
                        SQLToken::$typeToStr[$tokenType]);
        // We never get here.
        return FALSE;
    }

    /**
     * Same as expect() but returns the expected tokens value rather
     * than returning TRUE.
     *
     * @var integer $tokenType
     *  Token type as specified in SQLToken::TOK_* constants.
     *
     * @return boolean
     *  Returns token's value on matching expected token type.
     */
    protected function expectGetValue ($tokenType) {
        if (($ret = $this->acceptGetValue($tokenType)) !== FALSE) {
            return $ret;
        }
        $this->error('Unexpected symbol. Expected ' .
                        SQLToken::$typeToStr[$tokenType]);
        // We never get here.
        return FALSE;
    }


    // --- Recursive Descent Parser methods --- //

    protected function sql_like_query() {
        $this->nextToken();

        if ($this->accept(SQLToken::TOK_SELECT)) {
            $this->select();
        }
        if ($this->accept(SQLToken::TOK_WHERE)) {
            $this->where();
        }
        if ($this->accept(SQLToken::TOK_ORDERBY)) {
            $this->order_by();
        }
        if ($this->accept(SQLToken::TOK_LIMIT)) {
            $this->limit();
        }
        if ($this->accept(SQLToken::TOK_OFFSET)) {
            $this->offset();
        }

        if (! $this->_token->isType(SQLToken::TOK_NULL)) {
            $this->error('Syntax error');
        }
    }

    protected function select () {
        $this->expect(SQLToken::TOK_STR);
        while ($this->accept(SQLToken::TOK_COMMA)) {
            $this->expect(SQLToken::TOK_STR);
        }
    }

    protected function where () {
        if ($this->accept(SQLToken::TOK_OMIT)) {
            // This is the unary OMIT operator
        }

        $this->where_condition();
    }

    protected function where_condition () {
        $this->where_and();

        while ($this->_token->isType(SQLToken::TOK_OR) ||
                $this->_token->isType(SQLToken::TOK_OMIT)) {
            $this->nextToken();
            $this->where_and();
        }
    }

    protected function where_and () {
        $this->where_term();

        while ($this->_token->isType(SQLToken::TOK_AND)) {
            $this->nextToken();
            $this->where_term();
        }
    }

    protected function where_term () {
        if ($this->accept(SQLToken::TOK_LPAREN)) {
            $this->where_condition();
            $this->expect(SQLToken::TOK_RPAREN);
        } elseif ($this->accept(SQLToken::TOK_STR)) {
            if ($this->_token->isType(array(SQLToken::TOK_EQ,
                                            SQLToken::TOK_LT,
                                            SQLToken::TOK_GT,
                                            SQLToken::TOK_LTEQ,
                                            SQLToken::TOK_GTEQ,
                                            SQLToken::TOK_LIKE,
                                            ))) {
                $this->nextToken();
                $this->expect(SQLToken::TOK_STR);
            } else {
                $this->error('Syntax error');
            }
        } else {
            $this->error('Syntax error');
            $this->nextToken();
        }
    }

    protected function order_by () {
        $this->expect(SQLToken::TOK_STR);
        if ($this->_token->isType(SQLToken::TOK_ASC) ||
                $this->_token->isType(SQLToken::TOK_DESC)) {
            $this->nextToken();
        }

        while ($this->accept(SQLToken::TOK_COMMA)) {
            $this->expect(SQLToken::TOK_STR);
            if ($this->_token->isType(SQLToken::TOK_ASC) ||
                    $this->_token->isType(SQLToken::TOK_DESC)) {
                $this->nextToken();
            }
        }
    }

    protected function limit () {
        $this->expect(SQLToken::TOK_STR);
    }


    protected function offset () {
        $this->expect(SQLToken::TOK_STR);
    }

    // --- End of Recursive Descent Parser methods --- //

};
