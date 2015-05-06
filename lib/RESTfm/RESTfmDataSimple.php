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

require_once 'RESTfmData.php';

/**
 * Simple / convenience methods for reducing effort needed to get data into
 * RESTfmData.
 */
class RESTfmDataSimple extends RESTfmData {

    /**
     * Convenience method to push a Row of 'data' and 'meta' at the same
     * time.
     *
     * Push an associative array comprising one record, and any associated
     * meta data.
     *
     * @param array $assoc
     *  Associative array of fieldName->value pairs making up one
     *  record. If NULL, only the $recordID meta data is pushed.
     * @param string $recordID
     *  (Optional) record ID associated with record being pushed. If null,
     *  will be automatically generated. Do not mix automatically generated
     *  IDs with explicitly set ones, as clashes may occur.
     * @param string $href
     *  (Optional) href meta data associated with record being pushed.
     */
    public function pushDataRow($assoc, $recordID = NULL, $href = NULL) {
        if (!isset($assoc) && !isset($recordID) && !isset($href)) {
            return;
        }

        $meta = array();

        /*
        if (!isset($recordID)) {
            $recordID = 'auto.'.$this->_lastRecordID++;
        }
        $meta['recordID'] = $recordID;
        */
        if (isset($recordID)) {
            $meta['recordID'] = $recordID;
        }

        if (isset($href)) {
            $meta['href'] = $href;
        }

        $this->setSectionData('meta', $recordID, $meta);

        if ($assoc !== NULL) {
            $this->setSectionData('data', $recordID, $assoc);
        }
    }

    /**
     * Push an associative array comprising meta data for one field.
     *
     * @param string $fieldName
     *  Name of field.
     *
     * @param array $fieldMeta
     *  Associative array of name->value pairs describing one field.
     */
    public function pushFieldMeta($fieldName, $fieldMeta) {
        $this->setSectionData('metaField', $fieldName,
                                array( 'name' => $fieldName, ) + $fieldMeta
                              );
    }

    /**
     * Retrieve value of specified $fieldName -> $metaName from 'metaField'
     * section.
     *
     * @param string $fieldName
     *  Name of field to fetch meta data for.
     * @param string $metaName
     *  Name of meta data to fetch from $fieldName.
     *
     * @return mixed
     *  Value of field meta data OR FALSE if non-existent.
     *
     *  WARNING: Returning FALSE is silly, the only acceptable failure
     *  is raising an exception - GAV.
     */
    public function getFieldMetaValue($fieldName, $metaName) {
        if ($this->sectionIndexExists('metaField', $fieldName)) {
            $fieldMeta = $this->getSectionData('metaField', $fieldName);
            if (isset($fieldMeta[$metaName])) {
                return $fieldMeta[$metaName];
            }
        }

        return FALSE;
    }

    /**
     * Push a generic $fieldName, $fieldData pair.
     *
     * Note: info names starting with X-RESTfm* are reserved for status code
     * information.
     *
     * @param string $fieldName
     *  Unique field name.
     * @param string $fieldData
     *  Arbitrary data string.
     */
    public function pushInfo($fieldName, $fieldData) {
        $this->setSectionData('info', $fieldName, $fieldData);
    }

    /**
     * Push the provided navigation name and href.
     *
     * @param string $name
     *  Name of navigation function 'start', 'next', etc.
     * @param string $href
     *  URI for navigation name.
     */
    public function pushNav($name, $href) {
        $this->setSectionData('nav', NULL, array(
                                            'name'   =>  $name,
                                            'href'  =>  $href,
                                            )
                            );
    }

    // --- Protected Properties --- //

    /**
     * @var string last used recordID.
     *  Internal var to store last used recordID when automatically generated.
     */
    protected $_lastRecordID = 1;

}
