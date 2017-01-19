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

/**
 * Section access interface for export formats.
 */
class RESTfmMessageSection implements RESTfmMessageSectionInterface {

    // @var integer number of dimensions.
    protected $_dimensions = 0;

    // @var string section name.
    protected $_name = "";

    // @var array of assoc OR array of array of assoc.
    protected $_rows = array();

    /**
     * Get number of dimensions  of data for this section.
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
     * Returns an array of one or more rows.
     *  Note: A section with only one dimension has only one row.
     *  Note: A section with two dimensions may have more than one row.
     *
     * @return array of section data in the form of:
     *    1 dimensional:
     *    array('key' => 'val', ...)
     *   OR
     *    2 dimensional:
     *    array(
     *      array('key' => 'val', ...),
     *      ...
     *    ))
     *
     * @var array of assoc OR array of array of assoc.
     */
    public function getRows () {
        return $this->_rows;
    }

    /**
     * RESTfmMessage internal function.
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
     * RESTfmMessage internal function.
     * Get reference to internal _rows array.
     *
     * @return arrayref _rows array.
     */
    public function &_getRowsReference () {
        return $this->_rows;
    }
};
