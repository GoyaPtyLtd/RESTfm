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

namespace RESTfm\MessageInterface;

/**
 * Section access interface for export formats.
 */
interface SectionInterface {

    /**
     * Get number of dimensions of data for this section.
     *
     * May be 1 or 2.
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
    public function getRows ();
};
