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

require_once('SQLParserException.php');
require_once('SQLToken.php');

/**
 * SQL-like syntax lexer.
 */
class SQLLexer {

    /**
     * @var array
     *  Array of token regular expressions.
     *
     * Note: These are traversed in order, earlier have higher matching
     *       precedence than latter.
     */
    protected $tokenRegex = array(
        SQLToken::TOK_LPAREN        => '\(',
        SQLToken::TOK_RPAREN        => '\)',
        SQLToken::TOK_LTEQ          => '<=',
        SQLToken::TOK_GTEQ          => '>=',
        SQLToken::TOK_EQ            => '=',
        SQLToken::TOK_LT            => '<',
        SQLToken::TOK_GT            => '>',
        SQLToken::TOK_ORDERBY       => 'ORDER\s+BY',
        SQLToken::TOK_SELECT        => 'SELECT',
        SQLToken::TOK_WHERE         => 'WHERE',
        SQLToken::TOK_LIKE          => 'LIKE',
        SQLToken::TOK_AND           => 'AND',
        SQLToken::TOK_OR            => 'OR',
        SQLToken::TOK_OMIT          => 'OMIT',
        SQLToken::TOK_ASC           => 'ASC',
        SQLToken::TOK_DESC          => 'DESC',
        SQLToken::TOK_LIMIT         => 'LIMIT',
        SQLToken::TOK_OFFSET        => 'OFFSET',
        SQLToken::TOK_COMMA         => ',',
        // String is: double quoted | single quoted | unquoted
        //  - Quoted strings allow for backslash escaped characters to pass
        //    through without removing the escape, including escaped quotes.
        //  - Unquoted strings may not contain any whitespace or operators.
        SQLToken::TOK_STR           => '("(\\.|[^\\"])*")|(\'(\\.|[^\\\'])*\')|([^\s\(\)=<>,"\']+)',
    );

    /**
     * @var string
     *  Input string for lexing.
     */
    private $_input;

    /**
     * @var integer
     *  Position in original input string for next token.
     */
    private $offset = 0;

    /**
     * Lexer constructor.
     *
     * @param string $s
     *  Input string for tokenisation.
     */
    public function __construct($s) {
        $this->_input = $s;
    }

    /**
     * Return next token from input string.
     *
     * @return SQLToken
     *  Will return SQLToken of type SQLToken::TOK_NULL when input string
     *  is exhausted.
     */
    public function getToken() {
        $token = new SQLToken();
        $token->type = SQLToken::TOK_NULL;
        $token->pos = $this->offset + 1;
        $token->value = '';

        if (strlen($this->_input) <= 0) {
            return $token;
        }

        foreach ($this->tokenRegex as $regexType => $regexString) {
            // Add generic whitespace matching and grouping to token regex.
            // Ensure case insensitive match.
            $regexString = '/^(\s*(' . $regexString . ')\s*)/i';
            $matches = array();
            //echo 'Input: ' . $this->_input . "\n";
            //echo 'Testing: ' . $regexString . "\n";
            if (preg_match($regexString, $this->_input, $matches)) {
                $token->type = $regexType;
                // Strip leading/trailing whitespace and quotes.
                $token->value = preg_replace('/(^\s*[\"\']?|[\"\']?\s*$)/', '', $matches[1]);
                $token->pos = $this->offset + 1;
                //$token->debugStr = $regexString;
                $this->offset += strlen($matches[1]);
                $this->_input = substr($this->_input, strlen($matches[1]));
                return $token;
            }
        }

        if ($token->isType(SQLToken::TOK_NULL)) {
            # There has been a tokenisation error.

            if (preg_match('/^["\']/', $this->_input)) {
                # Probably a runaway string.
                throw new SQLParserException(
                    'Possible runaway string beginning at position ' . ($this->offset + 1). ': ' . $this->_input,
                    SQLParserException::EX_LEXER
                );
            }

            throw new SQLParserException(
                'No matching symbol at position ' . ($this->offset + 1). ': ' . $this->_input,
                SQLParserException::EX_PARSER
            );
        }
    }
}
