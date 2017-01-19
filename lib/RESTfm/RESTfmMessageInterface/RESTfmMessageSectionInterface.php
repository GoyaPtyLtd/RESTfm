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
interface RESTfmMessageSectionInterface {

    /**
     * Get number of dimensions  of data for this section.
     *
     * @return integer number of dimensions.
     */
    public function getDimensions ();

    /**
     * Get name of this section.
     *
     * @return string section name.
     */
    public function getName ();

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
     * @return array of assoc OR array of array of assoc.
     */
    public function getRows ();
};
