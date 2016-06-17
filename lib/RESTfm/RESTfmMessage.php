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
     */
    public function getRows () {
        return $this->_rows;
    }

    /**
     * RESTfmMessage internal function.
     */
    public function __construct ($name, $dimensions) {
        $this->_name = $name;
        $this->_dimensions = $dimensions;
    }

    /**
     * RESTfmMessage internal function.
     */
    public function &_getRowsReference () {
        return $this->_rows;
    }
}

class RESTfmMessage implements RESTfmMessageInterface {

    // -- Sections -- //

    protected $_info = array();         /// @var array 1 dimensional.
    protected $_metaFields = array();   /// @var array of RESTfmMessageRow
    protected $_multistatus = array();  /// @var array of RESTfmMessageMultistatus
    protected $_navs = array();         /// @var array of RESTfmMessageRow
    protected $_records = array();      /// @var array of RESTfmMessageRecordInterface

    /**
     * @var array of known section names.
     */
    protected $_knownSections = array('meta', 'data', 'info',
                                      'metaField', 'multistatus', 'nav');

    /**
     * @var associative array of recordId -> record index
     *  for identifying $_records[] by recordId.
     */
    protected $_recordIdMap = array();

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
     * @param RESTfmMessageRow $metaField
     */
    public function addMetaField (RESTfmMessageRow $metaField) {
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
     * @param RESTfmMessageMultistatus $multistatus
     */
    public function addMultistatus (RESTfmMessageMultistatus $multistatus) {
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
     * @param RESTfmMessageRow $nav
     */
    public function addNav (RESTfmMessageRow $nav) {
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
     * @param RESTfmMessageRecord $record
     */
    public function addRecord (RESTfmMessageRecord $record) {
        $this->_records[] = $record;

        $recordId = $record->getRecordId();
        if ($recordId !== NULL) {
            $recordIndex = count($this->_records) - 1;
            $this->_recordIdMap[$recordId] = $recordIndex;
        }
    }

    /**
     * @return array of RESTfmMessageRecordInterface.
     */
    public function getRecords () {
        return $this->_records;
    }

    /**
     * Return a single record identified by $recordId
     *
     * @param string $recordId
     *
     * @return RESTfmMessageRecordInterface or NULL if $recordId does not exist.
     */
    public function getRecordByRecordId ($recordId) {
        if (isset($this->_recordIdMap[$recordId])) {
            return $this->_recordIdMap[$recordId];
        }
    }


    // --- Access methods for reading data as sections (export formats) --- //

    /**
     * @return array of strings of available section names.
     *      Section names are: meta, data, info, metaField, multistatus, nav
     */
    public function getSectionNames () {
        $availableSections = array();

        // Sort as 'meta', 'data', 'info', <any other>.
        if (!empty($this->_records)) {
            $availableSections[] = 'meta';
            $availableSections[] = 'data';
        }
        if (!empty($this->_info)) { $availableSections[] = 'info'; }
        if (!empty($this->_metaFields)) { $availableSections[] = 'metaField'; }
        if (!empty($this->_multistatus)) { $availableSections[] = 'multistatus'; }
        if (!empty($this->_navs)) { $availableSections[] = 'nav'; }

        return $availableSections;
    }

    /**
     * @param string $sectionName
     *
     * @return RESTfmMessageSectionInterface
     */
    public function getSection ($sectionName) {
        switch ($sectionName) {
            case 'meta':
                $section = new RESTfmMessageSection($sectionName, 2);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_records as $record) {
                    $sectionRows[] = &$record->_getMetaReference();
                }
                return $section;
                break;

            case 'data':
                $section = new RESTfmMessageSection($sectionName, 2);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_records as $record) {
                    $sectionRows[] = &$record->_getDataReference();
                }
                return $section;
                break;

            case 'info':
                $section = new RESTfmMessageSection($sectionName, 1);
                $sectionRows = &$section->_getRowsReference();
                $sectionRows[] = &$this->_info;
                return $section;
                break;

            case 'metaField':
                $section = new RESTfmMessageSection($sectionName, 2);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_metaFields as $row) {
                    $sectionRows[] = &$row->_getDataReference();
                }
                return $section;
                break;

            case 'multistatus':
                $section = new RESTfmMessageSection($sectionName, 2);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_multistatus as $row) {
                    $sectionRows[] = &$row->_getMultistatusReference();
                }
                return $section;
                break;

            case 'nav':
                $section = new RESTfmMessageSection($sectionName, 2);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_navs as $row) {
                    $sectionRows[] = &$row->_getDataReference();
                }
                return $section;
                break;
        }
    }

    /**
     * @param string $sectionName section name.
     * @param array of section data.
     *  With section data in the form of:
     *    1 dimensional:
     *    array('key' => 'val', ...)
     *   OR
     *    2 dimensional:
     *    array(
     *      array('key' => 'val', ...),
     *      ...
     *    ))
     */
    public function setSection ($sectionName, $sectionData) {
        switch ($sectionName) {
            case 'meta':
                $index = 0;
                foreach ($sectionData as $rowIndex => $row) {
                    if (isset($this->_records[$index])) {
                        $record = $this->_records[$index];
                    } else {
                        $record = new RESTfmMessageRecord();
                        $this->addRecord($record);
                    }
                    foreach ($row as $key => $val) {
                        switch ($key) {
                            case 'href':
                                $record->setHref($val);
                                break;
                            case 'recordID':
                                $record->setRecordId($val);
                                $this->_recordIdMap[$val] = $index;
                                break;
                        }
                    }
                    $index++;
                }
                break;

            case 'data':
                $index = 0;
                foreach ($sectionData as $rowIndex => $row) {
                    if (isset($this->_records[$index])) {
                        $record = $this->_records[$index];
                    } else {
                        $record = new RESTfmMessageRecord();
                        $this->addRecord($record);
                    }
                    $record->setData($row);
                    $index++;
                }
                break;

            case 'info':
                foreach ($sectionData as $key => $val) {
                    $this->_info[$key] = $val;
                }
                break;

            case 'metaField':
                foreach ($sectionData as $rowIndex => $row) {
                    $metaField = new RESTfmMessageRow();
                    $metaField->setData($row);
                    $this->addMetaField($metaField);
                }
                break;

            case 'multistatus':
                foreach ($sectionData as $rowIndex => $row) {
                    $multistatus = new RESTfmMessageMultistatus();
                    foreach ($row as $key => $val) {
                        switch ($key) {
                            case 'index':
                                $multistatus->setIndex($val);
                                break;
                            case 'Status':
                                $multistatus->setStatus($val);
                                break;
                            case 'Reason':
                                $multistatus->setReason($val);
                                break;
                            case 'recordID':
                                $multistatus->setRecordId($val);
                                break;
                        }
                    }
                    $this->addMultistatus($multistatus);
                }
                break;

            case 'nav':
                foreach ($sectionData as $rowIndex => $row) {
                    $nav = new RESTfmMessageRow();
                    $nav->setData($row);
                    $this->addNav($nav);
                }
                break;
        }
    }

    /**
     * @return associative array of all sections and data.
     *  With section(s) in the mixed form(s) of:
     *    1 dimensional:
     *    array('sectionNameX' => array('key' => 'val', ...))
     *    2 dimensional:
     *    array('sectionNameY' => array(
     *                              array('key' => 'val', ...),
     *                              ...
     *                           ))
     */
    public function exportArray () {
        $export = array();

        foreach ($this->getSectionNames() as $sectionName) {
            $sectionData = array();
            $section = $this->getSection($sectionName);
            if ($section->getDimensions() == 1) {
                $sectionRows = &$section->_getRowsreference();
                $sectionData = &$sectionRows[0];
            } elseif ($section->getDimensions() == 2) {
                $sectionData = &$section->_getRowsreference();
            }
            $export[] = array($sectionName => $sectionData);
        }

        return $export;
    }

    /**
     * @param associative array $array of section(s) and data.
     *  With section(s) in the mixed form(s) of:
     *    1 dimensional:
     *    array('sectionNameX' => array('key' => 'val', ...))
     *    2 dimensional:
     *    array('sectionNameY' => array(
     *                              array('key' => 'val', ...),
     *                              ...
     *                           ))
     */
    public function importArray ($array) {
        foreach ($array as $sectionName => $sectionData) {
            $this->setSection($sectionName, $sectionData);
        }
    }

    /**
     * Make a human readable string of all sections and data.
     *
     * @return string
     */
    public function __toString () {
        $s = '';
        foreach ($this->getSectionNames() as $sectionName) {
            $s .= $sectionName . ":\n";

            $section = $this->getSection($sectionName);
            if ($section->getDimensions() == 1) {
                $sectionRows = &$section->_getRowsreference();
                $sectionData = &$sectionRows[0];
                foreach ($sectionData as $key => $value) {
                    $s .= '  ' . $key . '="' . addslashes($value) . '"' . "\n";
                }
            } elseif ($section->getDimensions() == 2) {
                $sectionData = &$section->_getRowsreference();
                foreach ($sectionData as $index => $row) {
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

};
