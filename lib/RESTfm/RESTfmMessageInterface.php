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
     *  Set/update row data with provided array of key/value pairs.
     */
    public function setData ($assocArray);

    /**
     * @return integer Unique index of this row.
     */
    public function getRowIndex ();
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
     * @param integer $index
     */
    public function setIndex ($index);

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
     * Returns an array of one or more message rows.
     *  Note: A section with only one dimension has only one row.
     *  Note: A section with two dimensions may have more than one row.
     *
     * @return array of RESTfmMessageRowInterface.
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


    // --- Access methods for reading data as sections (export formats) --- //

    /**
     * @return array of strings of available section names.
     *      Section names are: nav, data, meta, metaField, info, multistatus
     *
     */
    public function getSectionNames ();

    /**
     * @param string $name section name.
     *
     * @return RESTfmMessageSectionInterface
     */
    public function getSection ($name);

    /**
     * Make a human readable string from stored contents.
     *
     * @return string
     */
    public function __toString ();
}
