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

class RESTfmMessageRecordTest extends PHPUnit_Framework_TestCase {

    public function testConstructorAndGet() {
        $rowData = array ('field1'  => 'value1',
                          'field2'  => 'value2');

        $record = new RESTfmMessageRecord( 'test0', 'href://here', $rowData);

        $this->assertEquals($record->getHref(), 'href://here');
        $this->assertEquals($record->getRecordId(), 'test0');

        $arrayDiff = array_diff($rowData, $record->getData());
        $this->assertEmpty($arrayDiff);
    }

    public function testSetAndGetHref() {
        $record = new RESTfmMessageRecord();

        $record->setHref('test');

        $this->assertEquals($record->getHref(), 'test');
    }

    public function testSetAndGetRecordId() {
        $record = new RESTfmMessageRecord();

        $record->setRecordId('test2');

        $this->assertEquals($record->getRecordId(), 'test2');
    }
};
