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

use RESTfm\SQLParser\SQLParser;
use RESTfm\SQLParser\SQLToken;
use RESTfm\SQLParser\SQLParserException;
use RESTfm\ResponseException;

/**
 * FileMaker Data API specific implementation of SQLParser class.
 */
class FileMakerDataApiSQLParser extends SQLParser {

    /**
     * Override parent class parse() method.
     *
     * @throws \RESTfm\ResponseException
     *   On error.
     */
    public function parse() {
      try {
        parent::parse();
      } catch (SQLParserException $e) {
         // Re-throw as a \RESTfm\ResponseException.
         throw new ResponseException('RFMfind SQL error: '.$e->getMessage(), ResponseException::BADREQUEST, $e);
      }
    }

    // -- Additional public methods -- //

    /**
     * Return the list of fieldNames from SELECT statement.
     */
    public function getSelectList () {
        return $this->_selectList;
    }

    /**
     * @return array
     *  Query as array of arrays
     */
    public function getQuery () {
        return $this->_query;
    }

    /**
     * @return array
     *  Sort as array of arrays
     */
    public function getSort () {
        return $this->_sort;
    }

    /**
     * @return integer
     *  Maximum number of records to return.
     */
    public function getLimit () {
        return $this->_limit;
    }

    /**
     * @return integer
     *  Number of records to skip past.
     */
    public function getOffset () {
        return $this->_offset;
    }

    // -- Protected members and methods -- //

    /**
     * @var array
     *  A list of fieldNames from SELECT statement.
     */
    protected $_selectList = array();

    /**
     * @var array
     *  Query as array of arrays.
     */
    protected $_query = array();

    /**
     * @var array
     *  Sort as array of arrays.
     */
    protected $_sort = array();

    /**
     * @var integer
     *  Maximum number of records to return.
     */
    protected $_limit = NULL;

    /**
     * @var integer
     *  Number of records to skip past.
     */
    protected $_offset = NULL;

    /**
     * @var FileMaker_Command_FindRequest
     *  The current find request that AND'd criterion are added to.
     */
    protected $_findRequest = NULL;

    /**
     * @var boolean
     *  Set TRUE for next findRequest to be an omit request.
     */
    protected $_nextFindRequestIsOmit = FALSE;

    /**
     * @var integer
     *  Current parenthesis depth.
     */
    protected $_parenthesisDepth = 0;

    /**
     * Add the provided criterion to $this->_findRequest. If $this->_findRequest
     * is NULL, then a new find request is created first.
     *
     * @param string $fieldName
     *  Name of the field being tested.
     *
     * @param string $testValue
     *  Value of the field to test against.
     */
    protected function addCriterionToFindRequest ($fieldName, $testValue) {
        $appendDebug = 'Existing findRequest';
        if ($this->_findRequest === NULL) {
            $this->_findRequest = array();
            if ($this->_debug) {
                $appendDebug = 'New findRequest';
                if ($this->_nextFindRequestIsOmit) {
                    $appendDebug .= ', Omit';
                }
            }
        }

        $this->_findRequest[$fieldName] = $testValue;
        if ($this->_debug) {
            echo "addCriterionToFindRequest: $fieldName, $testValue ($appendDebug)\n";
        }
    }

    /**
     * Add $this->_findRequest to $this->_query, and reset to NULL.
     */
    protected function addFindRequestToQuery () {
        if ($this->_findRequest !== NULL) {
            if ($this->_debug) {
                echo "addFindRequestToQuery\n";
            }
            if ($this->_nextFindRequestIsOmit) {
                $this->_findRequest['omit'] = 'true';
                $this->_nextFindRequestIsOmit = FALSE;
            }
            $this->_query[] = $this->_findRequest;
            $this->_findRequest = NULL;
        }
    }

    // -- Override Recursive Descent Parser methods for FM Data API find -- //

    /*
     * We don't really care about parenthesis here as FileMaker 'find' is
     * limited to a consecutive list of OR'd 'find requests'. Each
     * 'find request' being a set of AND'd fieldName/testValue pairs. The
     * 'omit' type of 'find request' is expected to be last as it simply
     * removes any fieldName/testValue matches from the results.
     *
     * examples:
     *
     * WHERE (<term1> AND <term2>) OR (<term2> AND <term3>) OMIT (<term4> AND <term5>)
     * WHERE OMIT (<term1> AND <term2>)
     *
     * - <term?> is in the form 'fieldName <operator> testValue' )
     * - Each AND'd group of terms in parenthesis is a single 'find request'
     * - Due to AND precedence over OR|OMIT, these are equivalent:
     *
     * WHERE <term1> AND <term2> OR <term2> AND <term3> OMIT <term4> AND <term5>
     * WHERE OMIT <term1> AND <term2>
     *
     * Since FileMaker has no equivalent to OR within a 'find request', the
     * following is illegal (although otherwise appears to be valid SQL):
     *
     * **ILLEGAL**: WHERE (<term1> OR <term2>) AND (<term3> OR <term4)
     * **ILLEGAL**: WHERE OMIT (<term1> OR <term2>)
     *
     * So, to simplify checking for this, all occurrences of OR or OMIT within
     * parenthesis will cause a syntax error.
     */

    protected function select () {
        $fieldName = $this->expectGetValue(SQLToken::TOK_STR);
        $this->_selectList[] = $fieldName;
        if ($this->_debug) {
            echo 'select: ' . $fieldName . "\n";
        }

        while ($this->accept(SQLToken::TOK_COMMA)) {
            $fieldName = $this->expectGetValue(SQLToken::TOK_STR);
            $this->_selectList[] = $fieldName;
            if ($this->_debug) {
                echo 'select: ' . $fieldName . "\n";
            }
        }
    }

    protected function where () {
        if ($this->accept(SQLToken::TOK_OMIT)) {
            // This is the unary OMIT operator
            $this->_nextFindRequestIsOmit = TRUE;
            if ($this->_debug) {
                echo 'where: next find request is omit.' . "\n";
            }
        }

        $this->where_condition();

        $this->addFindRequestToQuery();
    }


    protected function where_condition () {
        $this->where_and();

        while ($this->_token->isType(SQLToken::TOK_OR) ||
                $this->_token->isType(SQLToken::TOK_OMIT)) {
            if ($this->_parenthesisDepth > 0) {
                $this->error('Syntax error. Symbol not allowed within parenthesis');
            }
            $this->addFindRequestToQuery();
            if ($this->_token->isType(SQLToken::TOK_OMIT)) {
                $this->_nextFindRequestIsOmit = TRUE;
                if ($this->_debug) {
                    echo 'where_condition: next find request is omit.' . "\n";
                }
            }
            $this->nextToken();
            $this->where_and();
        }
    }

    protected function where_term () {
        if ($this->accept(SQLToken::TOK_LPAREN)) {
            $this->_parenthesisDepth++;
            $this->where_condition();
            $this->expect(SQLToken::TOK_RPAREN);
            $this->_parenthesisDepth--;
        } elseif ( ($fieldName = $this->acceptGetValue(SQLToken::TOK_STR)) !== FALSE ) {
            if ($this->_token->isType(array(SQLToken::TOK_EQ,
                                            SQLToken::TOK_LT,
                                            SQLToken::TOK_GT,
                                            SQLToken::TOK_LTEQ,
                                            SQLToken::TOK_GTEQ,
                                            SQLToken::TOK_LIKE,
                                            ))) {

                $valueOperator = '';
                if ($this->_token->isType(SQLToken::TOK_LIKE)) {
                    // Drop the LIKE operator, the real operator is a part of
                    // the next string token. e.g. "==fred" or "john*"
                } else {
                    // Keep the operator to prepend to the next string token.
                    // e.g. "<="."30"
                    $valueOperator = $this->_token->value;
                }

                $this->nextToken();
                $testValue = $valueOperator . $this->expectGetValue(SQLToken::TOK_STR);

                $this->addCriterionToFindRequest($fieldName, $testValue);
            } else {
                $this->error('Syntax error');
            }
        } else {
            $this->error('Syntax error');
            $this->nextToken();
        }
    }

    protected function order_by () {
        $sortFieldName = $this->_token->value;
        $this->expect(SQLToken::TOK_STR);

        $sortOrder = NULL;
        if ($this->_token->isType(SQLToken::TOK_ASC)) {
            $this->nextToken();
            $sortOrder = 'ascend';
        } elseif ($this->_token->isType(SQLToken::TOK_DESC)) {
            $this->nextToken();
            $sortOrder = 'descend';
        }

        $this->_sort[] = array('fieldName' => $sortFieldName, 'sortOrder' => $sortOrder);
        if ($this->_debug) {
            echo "order_by: $sortFieldName, $sortOrder\n";
        }

        while ($this->accept(SQLToken::TOK_COMMA)) {
            $sortFieldName = $this->_token->value;
            $this->expect(SQLToken::TOK_STR);

            $sortOrder = NULL;
            if ($this->_token->isType(SQLToken::TOK_ASC)) {
                $this->nextToken();
                $sortOrder = 'ascend';
            } elseif ($this->_token->isType(SQLToken::TOK_DESC)) {
                $this->nextToken();
                $sortOrder = 'descend';
            }

            $this->_sort[] = array('fieldName' => $sortFieldName, 'sortOrder' => $sortOrder);
            if ($this->_debug) {
                echo "order_by: $sortFieldName, $sortOrder\n";
            }
        }
    }

    protected function limit () {
        $this->_limit = $this->_token->value;
        if ($this->_debug) {
            echo 'limit: ' . $this->_limit . "\n";
        }
        $this->expect(SQLToken::TOK_STR);
    }

    protected function offset () {
        $this->_offset = $this->_token->value;
        if ($this->_debug) {
            echo 'offset: ' . $this->_offset . "\n";
        }
        $this->expect(SQLToken::TOK_STR);
    }

}
