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

class RESTfmMessageRowTest extends PHPUnit_Framework_TestCase {

    static $data = array(
        'Field1' => 'Value1',
        'Field2' => 'Value2',
    );

    public function testSetAndGetData() {
        $row = new RESTfmMessageRow();

        $row->setData(RESTfmMessageRowTest::$data);

        $getData = $row->getData();

        $arrayDiff = array_diff(RESTfmMessageRowTest::$data, $getData);
        $this->assertEmpty($arrayDiff);

        return $row;
    }

    /**
     * @depends testSetAndGetData
     */
    public function testSetAndGetField(RESTfmMessageRow $row) {
        $row->setField('Field3', 'Value3');
        $row->setField('Field4', 'Value4');

        $this->assertEquals($row->getField('Field3'), 'Value3');
        $this->assertEquals($row->getField('Field4'), 'Value4');

        return $row;
    }

    /**
     * @depends testSetAndGetField
     */
    public function testUnsetField(RESTfmMessageRow $row) {
        $this->assertEquals($row->getField('Field2'), 'Value2');

        $row->unsetField('Field2');

        $this->assertEquals($row->getField('Field2'), NULL);

       return $row;
    }

    /**
     * @depends testUnsetField
     */
    public function testDataReference(RESTfmMessageRow $row) {
        $this->assertEquals($row->getField('Field3'), 'Value3');

        $arrayRef = &$row->_getDataReference();

        $this->assertEquals($arrayRef['Field3'], 'Value3');
    }

};
