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

/**
 * RESTfmDataAbstract
 *
 * All data sent or recieved by a resource is encapsulated behind this
 * interface. Specifically records, but it is also used by non-record URIs
 * to present a uniform REST-like interface.
 *
 * Would prefer this to be an interface, not an abstract class. Poor calltip
 * support in Komodo make this impossible.
 */
abstract class RESTfmDataAbstract implements Iterator {

    /**
     * Create a new section containing one or two dimensional data.
     *
     * Current common section names are:
     *  nav, data, meta, metaField, info, multistatus
     *
     * Nothing happens if the section $name already exists.
     *
     * @param string $name
     * @param integer $dimensions
     *  Number of dimensions for subsequent add/get/update operations.
     *  - A one dimensional section will expect a single, unique,
     *      index/fieldName => value pair.
     *  - A two dimensional section will expect a complete set of
     *      fieldName => value pairs maintained as a row.
     */
    abstract public function addSection ($name, $dimensions = 2);

    /**
     * Check if section $name exists.
     *
     * @return boolean
     *  Returns TRUE is section exists.
     */
    abstract public function sectionExists ($name);

    /**
     * Check if section $name->$index exists.
     *
     * @return boolean
     *  Returns TRUE is section AND index exists.
     */
    abstract public function sectionIndexExists ($name, $index);

    /**
     * Get a list of all section names.
     *
     * @return Array
     *  Array of section names.
     */
    abstract public function getSectionNames ();

    /**
     * Get all data in section.
     *
     * @param string $name
     *
     * @return Array
     *  An array of all data in requested section.
     */
    abstract public function getSection ($name);

    /**
     * Get dimensions of section data.
     *
     * @param string $name
     *
     * @return integer
     */
    abstract public function getSectionDimensions ($name);

    /**
     * Get a count of elements in section.
     *
     * @param string $name
     *
     * @return integer
     */
    abstract public function getSectionCount ($name);

    /**
     * Get a count of elements in 2nd dimension of section.
     *
     * @param string $name
     * @param string $index
     *
     * @return integer
     *  Returns -1 if $name is not two dimensional.
     *  Returns -2 if $index is not in $name.
     */
    abstract public function getSectionCount2nd ($name, $index);

    /**
     * Add data to section.
     *
     * @param string $name
     *  If section $name does not yet exist, it is created.
     * @param string $index
     *  A NULL value will result in an automatic index value.
     * @param value/Array $data
     *  Value if section is one dimensional.
     *  Array if section is two dimnesional.
     */
    abstract public function setSectionData ($name, $index, $data);

    /**
     * Add data to 2nd dimension of 1st dimension in section.
     *
     * @param string $name
     *  If section $name does not yet exist, it is created.
     * @param string $index1
     *  If index of first dimension does not yet exist, it is created.
     * @param string $index2
     *  Index of second dimension to add.
     * @param $value
     *  Value if section is one dimensional.
     */
    abstract public function setSectionData2nd ($name, $index1, $index2, $value);

    /**
     * Get data from section.
     *
     * @param string $name Section name.
     * @param string $index
     *
     * @return mixed
     *  Returns value if section is one dimensional.
     *  Returns array if section is two dimensional.
     */
    abstract public function getSectionData ($name, $index);

    /**
     * Get data from 2nd dimension of 1st dimentsion in section.
     *
     * @param string $name Section name.
     * @param string $index1
     * @param string $index2
     *
     * @return mixed
     *  Returns value.
     */
    abstract public function getSectionData2nd ($name, $index1, $index2);

    /**
     * Delete data from section.
     *
     * @param string $name Section name.
     * @param string $index
     */
    abstract public function deleteSectionData ($name, $index);

    /**
     * When using the Iterator capabilities, set the section to be iterated.
     *
     * @param string $name Section name.
     */
    abstract public function setIteratorSection ($name);

    /**
     * Make a human readable string from stored contents.
     *
     * @return string
     */
    abstract public function __toString ();

}
