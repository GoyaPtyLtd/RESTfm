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

class SQLToken {
    /* Token types. */
    const   TOK_NULL        =  0,
            TOK_SELECT      =  1,
            TOK_WHERE       =  2,
            TOK_LPAREN      =  3,
            TOK_RPAREN      =  4,
            TOK_EQ          =  5,
            TOK_LT          =  6,
            TOK_GT          =  7,
            TOK_LTEQ        =  8,
            TOK_GTEQ        =  9,
            TOK_LIKE        = 10,
            TOK_AND         = 11,
            TOK_OR          = 12,
            TOK_OMIT        = 13,
            TOK_ORDERBY     = 14,
            TOK_ASC         = 15,
            TOK_DESC        = 16,
            TOK_LIMIT       = 17,
            TOK_OFFSET      = 18,
            TOK_COMMA       = 19,
            TOK_STR         = 99;

    /**
     * @var array
     *  Mapping between token type (integer) and a printable string.
     */
    public static $typeToStr = array(
        self::TOK_NULL          => 'NULL',
        self::TOK_SELECT        => 'SELECT',
        self::TOK_WHERE         => 'WHERE',
        self::TOK_LPAREN        => 'LPAREN',
        self::TOK_RPAREN        => 'RPAREN',
        self::TOK_EQ            => 'EQUAL',
        self::TOK_LT            => 'LESSTHAN',
        self::TOK_GT            => 'GREATHAN',
        self::TOK_LTEQ          => 'LESSTHANEQ',
        self::TOK_GTEQ          => 'GREATHANEQ',
        self::TOK_LIKE          => 'LIKE',
        self::TOK_AND           => 'AND',
        self::TOK_OR            => 'OR',
        self::TOK_OMIT          => 'OMIT',
        self::TOK_ORDERBY       => 'ORDER BY',
        self::TOK_ASC           => 'ASC',
        self::TOK_DESC          => 'DESC',
        self::TOK_LIMIT         => 'LIMIT',
        self::TOK_OFFSET        => 'OFFSET',
        self::TOK_COMMA         => 'COMMA',
        self::TOK_STR           => 'STRING',
    );

    /**
     * @var integer
     *  Token type from const table.
     */
    public $type;

    /**
     * @var string
     *  Literal string value of token from original input string.
     */
    public $value;

    /**
     * @var integer
     *  Character position of token in original input string. First
     *  character is 1 (not 0).
     */
    public $pos;

    /**
     * Compare a token's type to a provided SQLToken::TOK_* type.
     *
     * @param integer|array $type
     *  SQLToken::TOK_* to compare this token's type to, or an
     *  array(SQLToken::TOK_*, ...) that this token's type must exist in.
     *
     * @return boolean
     *  TRUE on matching types, else FALSE.
     */
    public function isType ($type) {
        if (is_array($type)) {
            return in_array($this->type, $type, TRUE);
        } else {
            return ($this->type === $type);
        }
    }

    /**
     * @return string
     *  A printable string representation of $this->type
     */
    public function type () {
       return self::$typeToStr[$this->type];
    }

    /**
     * @var string
     *  Some debugging information during development.
     */
    public $debugStr = NULL;

    /**
     * Magic method when this object is used as a string.
     */
    public function __toString () {
        $s = '';
        $s .= 'Type: ' . str_pad(self::$typeToStr[$this->type], 10);
        $s .= ', Pos: ' . str_pad($this->pos, 4, ' ', STR_PAD_LEFT);
        $s .= ', Value: ' . $this->value;
        if ($this->debugStr !== NULL) {
            $s .= ', Debug: ' . $this->debugStr;
        }

        return $s;
    }
}
