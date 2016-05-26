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

require_once 'RESTfmMessageInterface.php';

class RESTfmMessageRow implements RESTfmMessageRowInterface {

    protected $_data = array();

    /**
     * @return associative array of key/value pairs.
     */
    public function getData () {
        return $data;
    }

    /**
     * @param array $assocArray
     *  Set row data with provided array of key/value pairs.
     */
    public function setData ($assocArray) {
        $this->_data = $assocArray;
    }

    /**
     * @param string $fieldName
     *
     * @return mixed
     */
    public function getField ($fieldName) {
        if (isset($this->_data[$fieldName])) { return $this->_data[$fieldName]; }
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldValue
     */
    public function setField ($fieldName, $fieldValue) {
        $this->_data[$fieldName] = $fieldValue;
    }

    /**
     * @param string $fieldName
     */
    public function unsetField ($fieldName) {
        if (isset($this->_data[$fieldName])) { unset($this->_data[$fieldName]); }
    }

    /**
     * RESTfmMessage internal function.
     */
    public function &_getDataReference () {
        return $this->_data;
    }
}

class RESTfmMessageRecord extends RESTfmMessageRow implements RESTfmMessageRecordInterface {

    protected $_meta = array();

    /**
     * @return string
     */
    public function getHref () {
        if (isset($this->_meta['href'])) return $this->_meta['href'];
    }

    /**
     * @param string $href
     */
    public function setHref ($href) {
        $this->_meta['href'] = $href;
    }

    /**
     * @return string
     */
    public function getRecordId () {
        if (isset($this->_meta['recordID'])) return $this->_meta['recordID'];
    }

    /**
     * @param string $recordID
     */
    public function setRecordId ($recordID) {
        $this->_meta['recordID'] = $recordID;
    }

    /**
     * RESTfmMessage internal function.
     */
    public function &_getMetaReference () {
        return $this->_meta;
    }
}

class RESTfmMessageMultistatus implements RESTfmMessageMultistatusInterface {

    protected $_multiStatus = array();

    /**
     * @return integer
     */
    public function getIndex () {
        if (isset($this->_multiStatus['index'])) return $this->_multiStatus['index'];
    }

    /**
     * @param integer $dataRowIndex
     *  Index of row in request's data section that caused the error.
     */
    public function setIndex ($dataRowIndex) {
        $this->_multiStatus['index'] = $dataRowIndex;
    }

    /**
     * @return string
     */
    public function getStatus () {
        if (isset($this->_multiStatus['Status'])) return $this->_multiStatus['Status'];
    }

    /**
     * @param integer $statusCode
     */
    public function setStatus ($statusCode) {
        $this->_multiStatus['Status'] = $statusCode;
    }

    /**
     * @return string
     */
    public function getReason () {
        if (isset($this->_multiStatus['Reason'])) return $this->_multiStatus['Reason'];
    }

    /**
     * @param string $reasonMessage
     */
    public function setReason ($reasonMessage) {
        $this->_multiStatus['Reason'] = $statusCode;
    }

    /**
     * @return string
     */
    public function getRecordId () {
        if (isset($this->_multiStatus['recordID'])) return $this->_multiStatus['recordID'];
    }

    /**
     * @param string $recordId
     */
    public function setRecordId ($recordId) {
        $this->_multiStatus['recordID'] = $recordId;
    }

    /**
     * RESTfmMessage internal function.
     */
    public function &_getMultistatusReference () {
        return $this->_multiStatus;
    }
}

class RESTfmMessageSection implements RESTfmMessageSectionInterface {

    protected $_dimensions = 0;
    protected $_name = "";
    protected $_rows = array();

    /**
     * @return integer number of dimensions of data for this section.
     */
    public function getDimensions () {
        return $this->_dimensions;
    }

    /**
     * @return string name of this section.
     */
    public function getName () {
        return $this->_name;
    }

    /**
     * Returns an array of one or more message rows.
     *  Note: A section with only one dimension has only one row.
     *  Note: A section with two dimensions may have more than one row.
     *
     * @return array of RESTfmMessageRowInterface.
     */
    public function getRows () {
        return $this->_rows;
    }

    /**
     * RESTfmMessage internal function.
     */
    public function _setName ($name) {
        $this->_name = $name;
    }

    /**
     * RESTfmMessage internal function.
     */
    public function _setDimensions ($dimensions) {
        $this->_dimensions = $dimensions;
    }
}

class RESTfmMessage implements RESTfmMessageInterface {

    protected $_info = array();
    protected $_metaFields = array();
    protected $_multistatus = array();
    protected $_navs = array();
    protected $_records = array();

    // --- Access methods for inserting/manipulating data --- //

    /**
     * Add or update a key/value pair to 'info' section.
     *
     * @param string $key
     * @param string $val
     */
    public function addInfo ($key, $val) {
        $this->_info[$key] = $val;
    }

    /**
     * @return associative array of key/value pairs.
     */
    public function getInfo () {
        return $this->_info;
    }

    /**
     * Add a message row object to 'metaField' section.
     *
     * @param RESTfmMessageRowInterface $metaField
     */
    public function addMetaField (RESTfmMessageRowInterface $metaField) {
        $this->_metaFields[] = $metaField;
    }

    /**
     * @return array of RESTfmMessageRowInterface.
     */
    public function getMetaFields () {
        return $this->_metaFields;
    }

    /**
     * Add a message multistatus object to 'multistatus' section.
     *
     * @param RESTfmMessageMultistatusInterface $multistatus
     */
    public function addMultistatus (RESTfmMessageMultistatusInterface $multistatus) {
        $this->_multistatus[] = $multistatus;
    }

    /**
     * @return array of RESTfmMessageMultistatusInterface.
     */
    public function getMultistatus () {
        return $this->_multistatus;
    }

    /**
     * Add a message row object to 'nav' section.
     *
     * @param RESTfmMessageRowInterface $nav
     */
    public function addNav (RESTfmMessageRowInterface $nav) {
        $this->_navs[] = $nav;
    }

    /**
     * @return array of RESTfmMessageRowInterface.
     */
    public function getNavs () {
        return $this->_navs;
    }

    /**
     * Add a message record object that contains data for 'data' and 'meta'
     * sections.
     *
     * @param RESTfmMessageRecordInterface $record
     */
    public function addRecord (RESTfmMessageRecordInterface $record) {
        $this->_records[] = $record;
    }

    /**
     * @return array of RESTfmMessageRecordInterface.
     */
    public function getRecords () {
        return $this->_records;
    }


    // --- Access methods for reading data as sections (export formats) --- //

    /**
     * @return array of strings of available section names.
     *      Section names are: nav, data, meta, metaField, info, multistatus
     *
     */
    public function getSectionNames () {
        $availableSections = array();

        // Sort as 'meta', 'data', 'info', <any other>.
        if (!empty($this->_records)) {
            $availableSections['meta'] = TRUE;
            $availableSections['data'] = TRUE;
        }
        if (!empty($this->_info)) { $availableSections['info'] = TRUE; }
        if (!empty($this->_metaFields)) { $availableSections['metaField'] = TRUE; }
        if (!empty($this->_multistatus)) { $availableSections['multistatus'] = TRUE; }
        if (!empty($this->_navs)) { $availableSections['nav'] = TRUE; }

        return array_keys($availableSections);
    }

    /**
     * @param string $sectionName
     *
     * @return RESTfmMessageSectionInterface
     */
    public function getSection ($sectionName) {
        $section = new RESTfmMessageSection;
        $section->_setName($sectionName);
        $sectionRows = $section->getRowsReference();

        switch ($sectionName) {
            case 'meta':
                $section->_setDimensions = 2;
                foreach ($this->_records as $record) {
                    $sectionRows[] = &$record->_getMetaReference();
                }
                return $section;
                break;

            case 'data':
                $section->_setDimensions = 2;
                foreach ($this->_records as $record) {
                    $sectionRows[] = &$record->_getDataReference();
                }
                return $section;
                break;

            case 'info':
                $section->_setDimensions = 1;
                $sectionRows[] = &$this->_info;
                return $section;
                break;

            case 'metaField':
                $section->_setDimensions = 2;
                foreach ($this->_metaFields as $row) {
                    $sectionRows[] = &$row->_getDataReference();
                }
                return $section;
                break;

            case 'multistatus':
                $section->_setDimensions = 2;
                foreach ($this->_multistatus as $row) {
                    $sectionRows[] = &$row->_getMultistatusReference();
                }
                return $section;
                break;

            case 'nav':
                $section->_setDimensions = 2;
                foreach ($this->_navs as $row) {
                    $sectionRows[] = &$row->_getDataReference();
                }
                return $section;
                break;
        }
    }

    /**
     * Make a human readable string from stored contents.
     *
     * @return string
     */
    public function __toString () {

    }
}
