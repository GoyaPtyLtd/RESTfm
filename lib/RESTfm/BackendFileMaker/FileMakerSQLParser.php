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

require_once(__DIR__.'/../SQLParser/SQLParser.php');

/**
 * FileMaker specific implementation of SQLParser class.
 */
class FileMakerSQLParser extends SQLParser {

    /**
     * Override parent class constructor for FileMaker specific parameters.
     *
     * @param string $sqlQuery
     *  String containing SQL-like syntax to parse.
     *
     * @param FileMaker $_FM
     *  A FileMaker object.
     *
     * @param string $layout
     *  The layout that will be queried.
     */
    public function __construct($sqlQuery, $FM, $layout) {
        parent::__construct($sqlQuery);

        $this->_FM = $FM;
        $this->_layout = $layout;
    }

    /**
     * Override parent class parse() method.
     *
     * @throws RESTfmResponseException
     *   On error.
     */
    public function parse() {
      try {
        parent::parse();
      } catch (SQLParserException $e) {
         // Re-throw as a RESTfmResponseException.
         throw new RESTfmResponseException('RFMfind SQL error: '.$e->getMessage(), RESTfmResponseException::BADREQUEST, $e);
      }
    }

    // -- Additional public methods -- //

    /**
     * Return the constructed FileMaker find object based on the
     * parsing of the SQL-like query string provided at construction.
     */
    public function getFMFind () {
        // Ensure we at least have a 'find all' command.
        if ($this->_fmFindCommand === NULL) {
            $this->_fmFindCommand = $this->_FM->newFindAllCommand($this->_layout);
        }

        // Set the range now.
        $offset = 0;
        if ($this->_offset !== NULL) { $offset = $this->_offset; }
        $this->_fmFindCommand->setRange($offset, $this->_limit);

        return $this->_fmFindCommand;
    }

    /**
     * Return the list of fieldNames from SELECT statement.
     */
    public function getSelectList () {
        return $this->_selectList;
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
     * @var FileMaker_Command_CompoundFind
     *  The compound find request built up from the parsed SQL-like query
     *  string.
     */
    protected $_fmFindCommand = NULL;

    /**
     * @var array
     *  A list of fieldNames from SELECT statement.
     */
    protected $_selectList = array();

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
     * @var FileMaker
     *  A valid FileMaker object to build newCompoundFind() and
     *  newFindRequest() from.
     */
    protected $_FM;

    /**
     * @var string
     *  The layout name to be queried.
     */
    protected $_layout;

    /**
     * @var FileMaker_Command_FindRequest
     *  The current find request that AND'd criterion are added to.
     */
    protected $_findRequest = NULL;

    /**
     * @var integer
     *  Find request precedence for compound find add() calls.
     */
    protected $_findRequestPrecedence = 1;

    /**
     * @var integer
     *  Sort order precedence for compound find addSortRule() calls.
     */
    protected $_sortPrecedence = 1;

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
            $this->_findRequest = $this->_FM->newFindRequest($this->_layout);
            if ($this->_debug) {
                $appendDebug = 'New findRequest';
                if ($this->_nextFindRequestIsOmit) {
                    $appendDebug .= ', Omit';
                }
            }
            if ($this->_nextFindRequestIsOmit) {
                $this->_findRequest->setOmit(TRUE);
                $this->_nextFindRequestIsOmit = FALSE;
            }
        }

        $this->_findRequest->addFindCriterion($fieldName, $testValue);
        if ($this->_debug) {
            echo "addCriterionToFindRequest: $fieldName, $testValue ($appendDebug)\n";
        }
    }

    /**
     * Add $this->_findRequest to $this->_fmFindCommand, and reset to NULL.
     */
    protected function addFindRequestToCompoundFind () {
        if ($this->_findRequest !== NULL) {
            if ($this->_debug) {
                echo "addFindRequestToCompoundFind: $this->_findRequestPrecedence\n";
            }
            $this->_fmFindCommand->add($this->_findRequestPrecedence++, $this->_findRequest);
            $this->_findRequest = NULL;
        }
    }

    // -- Override Recursive Descent Parser methods for FM compound find -- //

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
        // We have a WHERE clause, we need a compound find.
        $this->_fmFindCommand = $this->_FM->newCompoundFindCommand($this->_layout);

        if ($this->accept(SQLToken::TOK_OMIT)) {
            // This is the unary OMIT operator
            $this->_nextFindRequestIsOmit = TRUE;
            if ($this->_debug) {
                echo 'where: next find request is omit.' . "\n";
            }
        }

        $this->where_condition();

        $this->addFindRequestToCompoundFind();
    }


    protected function where_condition () {
        $this->where_and();

        while ($this->_token->isType(SQLToken::TOK_OR) ||
                $this->_token->isType(SQLToken::TOK_OMIT)) {
            if ($this->_parenthesisDepth > 0) {
                $this->error('Syntax error. Symbol not allowed within parenthesis');
            }
            $this->addFindRequestToCompoundFind();
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
        // Ensure we at least have a 'find all' command.
        if ($this->_fmFindCommand === NULL) {
            $this->_fmFindCommand = $this->_FM->newFindAllCommand($this->_layout);
        }

        $sortFieldName = $this->_token->value;
        $this->expect(SQLToken::TOK_STR);

        $sortOrder = NULL;
        if ($this->_token->isType(SQLToken::TOK_ASC)) {
            $this->nextToken();
            $sortOrder = FILEMAKER_SORT_ASCEND;
        } elseif ($this->_token->isType(SQLToken::TOK_DESC)) {
            $this->nextToken();
            $sortOrder = FILEMAKER_SORT_DESCEND;
        }

        $this->_fmFindCommand->addSortRule($sortFieldName, $this->_sortPrecedence, $sortOrder);
        if ($this->_debug) {
            echo "order_by: addSortRule($sortFieldName, $this->_sortPrecedence, $sortOrder)\n";
        }
        $this->_sortPrecedence++;

        while ($this->accept(SQLToken::TOK_COMMA)) {
            $sortFieldName = $this->_token->value;
            $this->expect(SQLToken::TOK_STR);

            $sortOrder = NULL;
            if ($this->_token->isType(SQLToken::TOK_ASC)) {
                $this->nextToken();
                $sortOrder = FILEMAKER_SORT_ASCEND;
            } elseif ($this->_token->isType(SQLToken::TOK_DESC)) {
                $this->nextToken();
                $sortOrder = FILEMAKER_SORT_DESCEND;
            }

            $this->_fmFindCommand->addSortRule($sortFieldName, $this->_sortPrecedence, $sortOrder);
            if ($this->_debug) {
                echo "order_by: addSortRule($sortFieldName, $this->_sortPrecedence, $sortOrder)\n";
            }
            $this->_sortPrecedence++;
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
