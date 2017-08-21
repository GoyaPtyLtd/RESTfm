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
 * RESTfm\Message\MessageInterface
 *
 * This message interface provides access to the request/response data sent
 * between formats (web input/output) and backends (database input/output).
 *
 * In general:
 *   Request: import format -> Message -> backend
 *   Response: backend -> Message -> export format
 *
 * In practice, responses are created from raised exceptions as well.
 *
 * Not every request will create a Message as some requests contain
 * no actual data.
 *
 * Every response will contain data and so will create a Message.
 */
interface MessageInterface {

    // --- Access methods for managing data in rows. --- //

    /**
     * Set an 'info' key/value pair.
     *
     * @param string $key
     * @param string $val
     */
    public function setInfo ($key, $val);

    /**
     * Unset an 'info' key.
     *
     * @param string $key
     */
    public function unsetInfo ($key);

    /**
     * @param string $key
     * @return string $val
     */
    public function getInfo ($key);

    /**
     * @return array [ <key> => <val>, ... ]
     */
    public function getInfos ();

    /**
     * Set a 'metaField' fieldName/row pair.
     *
     * @param string $fieldName
     * @param RowAbstract $metaField
     */
    public function setMetaField ($fieldName, RowAbstract $metaField);

    /**
     * @param string $fieldName
     *
     * @return RowAbstract
     */
    public function getMetaField ($fieldName);

    /**
     * @return integer
     */
    public function getMetaFieldCount ();

    /**
     * @return array [ <fieldName> => <RowAbstract>, ...]
     */
    public function getMetaFields ();

    /**
     * Add a 'multistatus' object (row).
     *
     * @param MultistatusInterface $multistatus
     */
    public function addMultistatus (MultistatusInterface $multistatus);

    /**
     * @param integer $index
     *  Index to return if it exists.
     *
     * @return Multistatus
     */
    public function getMultistatus ($index);

    /**
     * @return integer
     */
    public function getMultistatusCount ();

    /**
     * @return array [ <Multistatus>, ... ]
     */
    public function getMultistatuses ();

    /**
     * Set a 'nav' name/href pair.
     *
     * @param string name
     * @param string href
     */
    public function setNav ($name, $href);

    /**
     * @param string name
     *
     * @return string href
     */
    public function getNav ($name);

    /**
     * @return array [ <name> => <href>, ... ]
     */
    public function getNavs ();

    /**
     * Add a 'data+meta' record object (row plus meta data).
     *
     * @param RecordAbstract $record
     */
    public function addRecord (RecordAbstract $record);

    /**
     * Return a record by index.
     *
     * @param integer $index
     *  Index of record to return, if it exists.
     *
     * @return RecordAbstract
     */
    public function getRecord ($index);

    /**
     * @return integer
     */
    public function getRecordCount ();

    /**
     * @return array [ <RecordAbstract>, ... ]
     */
    public function getRecords ();


    // --- Access methods for managing data in sections. --- //

    /**
     * @return array of strings of available section names.
     *      Section names are: meta, data, info, metaField, multistatus, nav
     */
    public function getSectionNames ();

    /**
     * @param string $sectionName
     *
     * @return SectionInterface
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
    public function exportArray ();

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
    public function importArray ($array);

    /**
     * Make a human readable string of all sections and data.
     *
     * @return string
     */
    public function __toString ();
};
