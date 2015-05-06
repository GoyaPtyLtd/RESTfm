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

require_once 'RESTfmDataAbstract.php';

class RESTfmData extends RESTfmDataAbstract {

    // --- Private properties --- //

    /**
     * @var Array $_arr
     *  Complete array containing all sections and data.
     */
    private $_arr = array();

    /**
     * @var Array $_sections
     *  Array of sectionName => dimensions pairs.
     */
    private $_sections = array();

    /**
     * @var string $_iteratorSection
     *  Set to current section under iteration.
     */
    private $_iteratorSection;


    // --- RESTfmDataAbstract implementation --- //

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
    public function addSection ($name, $dimensions = 2) {
        if (isset($this->_arr[$name])) {
            return;     // Already exists.
        }
        $this->_sections[$name] = $dimensions;
        $this->_arr[$name] = array();
    }

    /**
     * Check if section $name exists.
     *
     * @return boolean
     *  Returns TRUE is section exists.
     */
    public function sectionExists ($name) {
        return isset($this->_arr[$name]);
    }

    /**
     * Check if section $name->$index exists.
     *
     * @return boolean
     *  Returns TRUE is section AND index exists.
     */
    public function sectionIndexExists ($name, $index) {
        return isset($this->_arr[$name]) && isset($this->_arr[$name][$index]);
    }

    /**
     * Get a list of all section names.
     *
     * @return Array
     *  Array of section names.
     */
    public function getSectionNames () {
        if (RESTfmConfig::getVar('settings', 'formatNicely')) {
            // Prioritise some sections above others.
            $sectionPriority = function ($a, $b) {
                // Sort as 'meta', 'data', 'info', <any other>.
                if ($a == 'meta' ) { return -1; }
                if ($b == 'meta' ) { return  1; }
                if ($a == 'data') { return -1; }
                if ($b == 'data') { return  1; }
                if ($a == 'info') { return -1; }
                if ($b == 'info') { return  1; }
                return 0;
            };
            $sectionNames = array_keys($this->_sections);
            usort($sectionNames, $sectionPriority);
            return $sectionNames;
        }

        return array_keys($this->_sections);
    }

    /**
     * Get all data in section.
     *
     * @param string $name
     *
     * @return Array
     *  An array of all data in requested section.
     */
    public function getSection ($name) {
        return $this->_arr[$name];
    }

    /**
     * Get dimensions of section data.
     *
     * @param string $name
     *
     * @return integer
     */
    public function getSectionDimensions ($name) {
        return $this->_sections[$name];
    }

    /**
     * Get a count of elements in section.
     *
     * @param string $name
     *
     * @return integer
     */
    public function getSectionCount ($name) {
        return count($this->_arr[$name]);
    }

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
    public function getSectionCount2nd ($name, $index) {
        if ($this->_sections[$name] <  2) {
            return -1;
        }
        if (!isset($this->_arr[$name][$index])) {
            return -2;
        }
        return count($this->_arr[$name][$index]);
    }

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
    public function setSectionData ($name, $index, $data) {
        if (!isset($this->_arr[$name])) {           // Nonexistent section.
            if (is_array($data)) {
                $this->addSection($name, 2);
            } else {
                $this->addSection($name, 1);
            }
        }
        if ($index == NULL) {                       // Automatic indexing
            array_push($this->_arr[$name], $data);
        } else {
            $this->_arr[$name][$index] = $data;
        }
    }

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
    public function setSectionData2nd ($name, $index1, $index2, $value) {
        if (!isset($this->_arr[$name])) {           // Nonexistent section.
            $this->addSection($name, 2);
        }
        if ($this->_sections[$name] < 2) {          // Enforce two dimensions.
            return FALSE;
        }
        if (!isset($this->_arr[$name][$index1])) {  // Nonexistent index.
            $this->_arr[$name][$index1] = array();
        }
        $this->_arr[$name][$index1][$index2] = $value;
    }

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
    public function getSectionData ($name, $index) {
        return $this->_arr[$name][$index];
    }

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
    public function getSectionData2nd ($name, $index1, $index2) {
        return $this->_arr[$name][$index1][$index2];
    }

    /**
     * Delete data from section.
     *
     * @param string $name Section name.
     * @param string $index
     */
    public function deleteSectionData ($name, $index) {
        unset($this->_arr[$name][$index]);
    }

    /**
     * When using the Iterator capabilities, set the section to be iterated.
     *
     * @param string $name Section name.
     */
    public function setIteratorSection ($name) {
        if (isset($this->_arr[$name])) {
            $this->_iteratorSection = $name;
        } else {
            $this->_iteratorSection = NULL;
        }
    }

    /**
     * Make a human readable string from stored contents.
     *
     * @return string
     */
    public function __toString () {
        $s = '';
        foreach($this->_sections as $sectionName => $sectionDimensions) {
            $s .= $sectionName . ":\n";
            if ($sectionDimensions == 1) {
                foreach ($this->_arr[$sectionName] as $key => $value) {
                    $s .= '  ' . $key . '="' . addslashes($value) . '"' . "\n";
                }
            } elseif ($sectionDimensions== 2) {
                foreach ($this->_arr[$sectionName] as $index => $row) {
                    $s .= '  ' . $index . ":\n";
                    foreach ($row as $key => $value) {
                        $s .= '    ' . $key . '="' . addslashes($value) . '"' . "\n";
                    }
                }
            } else {
                $s .= '  ** Unknown format **.' . "\n";
            }
            $s .= "\n";
        }
        return $s;
    }

    // --- Iterator interface implementation --- //

    public function current() {
        if (isset($this->_arr[$this->_iteratorSection])) {
            return current($this->_arr[$this->_iteratorSection]);
        }
    }

    public function key() {
        if (isset($this->_arr[$this->_iteratorSection])) {
            return key($this->_arr[$this->_iteratorSection]);
        }
    }

    public function next() {
        if (isset($this->_arr[$this->_iteratorSection])) {
            return next($this->_arr[$this->_iteratorSection]);
        }
    }

    public function rewind() {
        if (isset($this->_arr[$this->_iteratorSection])) {
            return reset($this->_arr[$this->_iteratorSection]);
        }
    }

    public function valid() {
        if (isset($this->_arr[$this->_iteratorSection])) {
            return key($this->_arr[$this->_iteratorSection]) !== NULL;
        }
    }

};
