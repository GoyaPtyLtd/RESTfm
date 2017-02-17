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

namespace libTests\RESTfm\Message;

use RESTfm\Message\Row;

class RowTest extends \PHPUnit_Framework_TestCase {

    static $data = array(
        'Field1' => 'Value1',
        'Field2' => 'Value2',
    );

    public function testConstructorSet() {
        $row = new Row(array(
                    'Field98'    => 'Value98',
                    'Field99'    => 'Value99',
                ));

        $this->assertEquals($row['Field99'], 'Value99');
        $this->assertEquals($row['Field98'], 'Value98');

        return $row;
    }


    public function testSetDataAndGetDataReference() {
        $row = new Row();

        $row->setData(RowTest::$data);

        $arrayRef = &$row->_getDataReference();

        $arrayDiff = array_diff(RowTest::$data, $arrayRef);
        $this->assertEmpty($arrayDiff);

        return $row;
    }

    /**
     * @depends testSetDataAndGetDataReference
     */
    public function testIterator(Row $row) {
        foreach ($row as $fieldName => $val) {
            $this->assertEquals(RowTest::$data[$fieldName],
                                $val);
        }
        return $row;
    }


    /**
     * @depends testIterator
     */
    public function testSetAndGetField(Row $row) {

        $row['Field3'] = 'Value3';
        $row['Field4'] = 'Value4';

        $this->assertEquals($row['Field3'], 'Value3');
        $this->assertEquals($row['Field4'], 'Value4');

        return $row;
    }

    /**
     * @depends testSetAndGetField
     */
    public function testCountFields(Row $row) {
        $this->assertEquals(count($row), 4);

        return $row;
    }

    /**
     * @depends testCountFields
     */
    public function testIssetAndUnset(Row $row) {
        $this->assertTrue(isset($row['Field2']));

        unset($row['Field2']);

        $this->assertFalse(isset($row['Field2']));  // Calls offsetExists()

        $this->assertFalse($row['Field2']);         // Calls offsetGet()

        $this->assertArrayNotHasKey('Field2', (array)$row);

        return $row;
    }
};
