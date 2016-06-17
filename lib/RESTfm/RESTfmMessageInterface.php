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
  * A generic row interface for key/value pairs.
  */
interface RESTfmMessageRowInterface {

    /**
     * @return associative array of key/value pairs.
     */
    public function getData ();

    /**
     * @param array $assocArray
     *  Set row data with provided array of key/value pairs.
     */
    public function setData ($assocArray);

    /**
     * @param string $fieldName
     *
     * @return mixed
     */
    public function getField ($fieldName);

    /**
     * @param string $fieldName
     * @param mixed $fieldValue
     */
    public function setField ($fieldName, $fieldValue);

    /**
     * @param string $fieldName
     */
    public function unsetField ($fieldName);

}

/**
 * An extension of RESTfmMessageRowInterface with additional record related
 * metadata access methods.
 */
interface RESTfmMessageRecordInterface extends RESTfmMessageRowInterface {

    /**
     * @return string
     */
    public function getHref ();

    /**
     * @param string $href
     */
    public function setHref ($href);

    /**
     * @return string
     */
    public function getRecordId ();

    /**
     * @param string $recordId
     */
    public function setRecordId ($recordId);
}

/**
 * Multistatus interface.
 */
interface RESTfmMessageMultistatusInterface {

    /**
     * @return integer
     */
    public function getIndex ();

    /**
     * @param integer $dataRowIndex
     *  Index of row in request's data section that caused the error.
     */
    public function setIndex ($dataRowIndex);

    /**
     * @return string
     */
    public function getStatus ();

    /**
     * @param integer $statusCode
     */
    public function setStatus ($statusCode);

    /**
     * @return string
     */
    public function getReason ();

    /**
     * @param string $reasonMessage
     */
    public function setReason ($reasonMessage);

    /**
     * @return string
     */
    public function getRecordId ();

    /**
     * @param string $recordId
     */
    public function setRecordId ($recordId);
}

/**
 * Section access interface for export formats.
 */
interface RESTfmMessageSectionInterface {

    /**
     * @return integer number of dimensions of data for this section.
     */
    public function getDimensions ();

    /**
     * @return string name of this section.
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
     */
    public function getRows ();
}

/**
 * RESTfmMessageInterface
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
interface RESTfmMessageInterface {

    // --- Access methods for inserting/manipulating data --- //

    /**
     * Add or update a key/value pair to 'info' section.
     *
     * @param string $key
     * @param string $val
     */
    public function addInfo ($key, $val);

    /**
     * @return associative array of key/value pairs.
     */
    public function getInfo ();

    /**
     * Add a message row object to 'metaField' section.
     *
     * @param RESTfmMessageRowInterface $metaField
     */
    public function addMetaField (RESTfmMessageRowInterface $metaField);

    /**
     * @return array of RESTfmMessageRowInterface.
     */
    public function getMetaFields ();

    /**
     * Add a message multistatus object to 'multistatus' section.
     *
     * @param RESTfmMessageMultistatusInterface $multistatus
     */
    public function addMultistatus (RESTfmMessageMultistatusInterface $multistatus);

    /**
     * @return array of RESTfmMessageMultistatusInterface.
     */
    public function getMultistatus ();

    /**
     * Add a message row object to 'nav' section.
     *
     * @param RESTfmMessageRowInterface $nav
     */
    public function addNav (RESTfmMessageRowInterface $nav);

    /**
     * @return array of RESTfmMessageRowInterface.
     */
    public function getNavs ();

    /**
     * Add a message record object that contains data for 'data' and 'meta'
     * sections.
     *
     * @param RESTfmMessageRecordInterface $record
     */
    public function addRecord (RESTfmMessageRecordInterface $record);

    /**
     * @return array of RESTfmMessageRecordInterface.
     */
    public function getRecords ();

    /**
     * Return a single record identified by $recordId
     *
     * @param string $recordId
     *
     * @return RESTfmMessageRecordInterface or NULL if $recordId does not exist.
     */
    public function getRecordByRecordId ($recordId);


    // --- Access methods for reading data as sections (export formats) --- //

    /**
     * @return array of strings of available section names.
     *      Section names are: meta, data, info, metaField, multistatus, nav
     */
    public function getSectionNames ();

    /**
     * @param string $sectionName
     *
     * @return RESTfmMessageSectionInterface
     */
    public function getSection ($sectionName);

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
    public function setSection ($sectionName, $sectionData);

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
    public function exportArray ();

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
    public function importArray ($array);

    /**
     * Make a human readable string of all sections and data.
     *
     * @return string
     */
    public function __toString ();
}
