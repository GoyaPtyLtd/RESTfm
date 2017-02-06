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
 * RESTfmMessage
 *
 * This message interface provides access to the request/response data sent
 * between formats (web input/output) and backends (database input/output).
 *
 * In general:
 *   Request: import format -> RESTfmMessage -> backend
 *   Response: backend -> RESTfmMessage -> export format
 *
 * In practice, responses are created from raised exceptions as well.
 *
 * Not every request will create a RESTfmMessage as some requests contain
 * no actual data.
 *
 * Every response will contain data and so will create a RESTfmMessage.
 */
class RESTfmMessage implements RESTfmMessageInterface {

    /**
     * A message object for passing request/response data between formats
     *  (web input/output) and backends (database input/output).
     */
    public function __construct () {}

    // -- Sections -- //

    // @var array of key/value pairs.
    protected $_info = array();

    // @var array of fieldName/RESTfmMessageRow pairs.
    protected $_metaFields = array();

    // @var array of RESTfmMessageMultistatus
    protected $_multistatus = array();

    // @var array of name/href pairs.
    protected $_navs = array();

    // @var array of RESTfmMessageRecord
    protected $_records = array();

    /**
     * @var array of known section dimensions.
     */
    protected $_knownSectionDimensions = array(
        'meta'          => 2,
        'data'          => 2,
        'info'          => 1,
        'metaField'     => 2,
        'multistatus'   => 2,
        'nav'           => 1,
    );

    /**
     * @var associative array of recordId -> record index
     *  for identifying $_records[] by recordId.
        // TODO dump getRecordById
     */
    protected $_recordIdMap = array();

    // --- Access methods for managing data in rows. --- //

    /**
     * Set an 'info' key/value pair.
     *
     * @param string $key
     * @param string $val
     */
    public function setInfo ($key, $val) {
        $this->_info[$key] = $val;
    }

    /**
     * @param string $key
     * @return string $val
     */
    public function getInfo ($key) {
        if (isset($this->_info[$key])) {
            return $this->_info[$key];
        }
    }

    /**
     * Unset an 'info' key.
     *
     * @param string $key
     */
    public function unsetInfo ($key) {
        unset($this->_info[$key]);
    }

    /**
     * @return array [ <key> => <val>, ... ]
     */
    public function getInfos () {
        return $this->_info;
    }

    /**
     * Set a 'metaField' fieldName/row pair.
     *
     * @param string $fieldName
     * @param RESTfmMessageRow $metaField
     */
    public function setMetaField ($fieldName, RESTfmMessageRowAbstract $metaField) {
        $this->_metaFields[$fieldName] = $metaField;
    }

    /**
     * @param string $fieldName
     *
     * @return RESTfmMessageRow
     */
    public function getMetaField ($fieldName) {
        if (isset($this->_metaFields[$fieldName])) {
            return $this->_metaFields[$fieldName];
        }
    }

    /**
     * @return integer
     */
    public function getMetaFieldCount () {
        return count($this->_metaFields);
    }

    /**
     * @return array [ <fieldName> => <RESTfmMessageRow>, ...]
     */
    public function getMetaFields () {
        return $this->_metaFields;
    }

    /**
     * Add a 'multistatus' object (row).
     *
     * @param RESTfmMessageMultistatus $multistatus
     */
    public function addMultistatus (RESTfmMessageMultistatusInterface $multistatus) {
        $this->_multistatus[] = $multistatus;
    }

    /**
     * @param integer $index
     *  Index to return if it exists.
     *
     * @return RESTfmMessageMultistatus OR
     *          array [ <RESTfmMessageMultistatus>, ... ]
     */
    public function getMultistatus ($index) {
        if (isset($this->_multistatus[$index])) {
            return $this->_multistatus[$index];
        }
    }

    /**
     * @return array [ <RESTfmMessageMultistatus>, ... ]
     */
    public function getMultistatuses () {
        return $this->_multistatus;
    }

    /**
     * Set a 'nav' name/href pair.
     *
     * @param string name
     * @param string href
     */
    public function setNav ($name, $href) {
        $this->_navs[$name] = $href;
    }

    /**
     * @param string name
     *
     * @return string href
     */
    public function getNav ($name) {
        if (isset($this->_navs[$name])) {
            return $this->_navs[$name];
        }
    }

    /**
     * @return array [ <name> => <href>, ... ]
     */
    public function getNavs () {
        return $this->_navs;
    }

    /**
     * Add a 'data+meta' record object (row plus meta data).
     *
     * @param RESTfmMessageRecord $record
     */
    public function addRecord (RESTfmMessageRecordAbstract $record) {
        $this->_records[] = $record;

        // TODO dump getRecordById
        $recordId = $record->getRecordId();
        if ($recordId !== NULL) {
            // TODO profile this operation
            $recordIndex = count($this->_records) - 1;
            $this->_recordIdMap[$recordId] = $recordIndex;
        }
    }

    /**
     * Return a record by index.
     *
     * @param integer $index
     *  Index of record to return, if it exists.
     *
     * @return RESTfmMessageRecord
     */
    public function getRecord ($index) {
        if (isset($this->_records[$index])) {
            return $this->_records[$index];
        }
    }

    /**
     * @return integer
     */
    public function getRecordCount () {
        return count($this->_records);
    }

    /**
     * @return array of RESTfmMessageRecord
     */
    public function getRecords () {
        return $this->_records;
    }

    /**
     * Return a single record identified by $recordId
     *
     * @param string $recordId
     *
     * @return RESTfmMessageRecord or NULL if $recordId does not exist.
     */
    public function getRecordByRecordId ($recordId) {
        // TODO dump getRecordById
        if (isset($this->_recordIdMap[$recordId])) {
            return $this->_records[$this->_recordIdMap[$recordId]];
        }
    }

    // --- Access methods for managing data in sections. --- //

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
     * @return RESTfmMessageSection
     */
    public function getSection ($sectionName) {
        switch ($sectionName) {
            case 'meta':
                $section = new RESTfmMessageSection($sectionName,
                                $this->_knownSectionDimensions[$sectionName]);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_records as $record) {
                    $sectionRows[] = &$record->_getMetaReference();
                }
                return $section;
                break;

            case 'data':
                $section = new RESTfmMessageSection($sectionName,
                                $this->_knownSectionDimensions[$sectionName]);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_records as $record) {
                    $sectionRows[] = &$record->_getDataReference();
                }
                return $section;
                break;

            case 'info':
                $section = new RESTfmMessageSection($sectionName,
                                $this->_knownSectionDimensions[$sectionName]);
                $sectionRows = &$section->_getRowsReference();
                $sectionRows[] = &$this->_info;
                return $section;
                break;

            case 'metaField':
                $section = new RESTfmMessageSection($sectionName,
                                $this->_knownSectionDimensions[$sectionName]);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_metaFields as $row) {
                    $sectionRows[] = &$row->_getDataReference();
                }
                return $section;
                break;

            case 'multistatus':
                $section = new RESTfmMessageSection($sectionName,
                                $this->_knownSectionDimensions[$sectionName]);
                $sectionRows = &$section->_getRowsReference();
                foreach ($this->_multistatus as $row) {
                    $sectionRows[] = &$row->_getMultistatusReference();
                }
                return $section;
                break;

            case 'nav':
                $section = new RESTfmMessageSection($sectionName,
                                $this->_knownSectionDimensions[$sectionName]);
                $sectionRows = &$section->_getRowsReference();
                $sectionRows[] = &$this->_navs;
                return $section;
                break;
        }
    }

    /**
     * Set section data from provided array parameter.
     *
     * Is resilient to passing 1d array parameter as a single array, or as a
     * single array at index[0] of 2d array. Dimensionality is determined
     * internally by $sectionName, not parameter format.
     *
     * @param string $sectionName section name.
     * @param array of section data.
     *  With section data in the form of:
     *    1 dimensional:
     *      ['key' => 'val', ...]
     *      OR
     *      [['key' => 'var', ...]]
     *   OR
     *    2 dimensional:
     *      [
     *          ['key' => 'val', ...],
     *          ...
     *      ]
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
                // We are 1d, but allow a single 2d row.
                if (isset($sectionData[0]) && is_array($sectionData[0])) {
                    $sectionData = $sectionData[0];
                }
                foreach ($sectionData as $key => $val) {
                    $this->setInfo($key, $val);
                }
                break;

            case 'metaField':
                foreach ($sectionData as $rowIndex => $row) {
                    if (isset($row['name'])) {
                        $metaField = new RESTfmMessageRow();
                        $metaField->setData($row);
                        $this->setMetaField($row['name'], $metaField);
                    }
                }
                break;

            case 'multistatus':
                foreach ($sectionData as $rowIndex => $row) {
                    $multistatus = new RESTfmMessageMultistatus();
                    foreach ($row as $key => $val) {
                        switch ($key) {
                            // 'index' is deprecated for 'recordID' for
                            // consistency on bulk POST/CREATE operations.
                            //case 'index':
                            //    $multistatus->setIndex($val);
                            //    break;
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
                // We are 1d, but allow a single 2d row.
                if (isset($sectionData[0]) && is_array($sectionData[0])) {
                    $sectionData = $sectionData[0];
                }
                foreach ($sectionData as $name => $href) {
                    $this->setNav($name, $href);
                }
                break;
        }
    }

    /**
     * Export all sections as a single associative array.
     *
     * @return array of all sections and data.
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
            $export[$sectionName] = $sectionData;
        }

        return $export;
    }

    /**
     * Import sections and associated data from the provided array.
     *
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
            }

            $s .= "\n";
        }
        return $s;
    }

};
