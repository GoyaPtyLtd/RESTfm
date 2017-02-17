<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2016 Goya Pty Ltd.
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

namespace RESTfm\Message;

/**
 * Section access interface for export formats.
 */
class Section implements SectionInterface {

    // @var integer number of dimensions.
    protected $_dimensions = 0;

    // @var string section name.
    protected $_name = "";

    // @var array of assoc arrays.
    protected $_rows = array();

    /**
     * Get number of dimensions of data for this section.
     *
     * May be 1 or 2.
     *
     * @return integer number of dimensions.
     */
    public function getDimensions () {
        return $this->_dimensions;
    }

    /**
     * Get name of this section.
     *
     * @return string section name.
     */
    public function getName () {
        return $this->_name;
    }

    /**
     * Returns section data as an array of one or more rows.
     *  - A section with only one dimension has only one row.
     *  - A section with two dimensions may have more than one row.
     *  - In the form of:
     *    [
     *      [<key> => <val>, ...],
     *      ...
     *    ]
     *
     * @return array of assoc arrays.
     */
    public function getRows () {
        return $this->_rows;
    }

    /**
     * RESTfm\Message internal function.
     * Constructor.
     *
     * @param string section name.
     * @param integer section dimensions.
     */
    public function __construct ($name, $dimensions) {
        $this->_name = $name;
        $this->_dimensions = $dimensions;
    }

    /**
     * RESTfm\Message internal function.
     * Get reference to internal _rows array.
     *
     * @return arrayref _rows array.
     */
    public function &_getRowsReference () {
        return $this->_rows;
    }
};
