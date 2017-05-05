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

namespace RESTfmTests\Message;

use RESTfm\Message\Section;

class SectionTest extends \PHPUnit_Framework_TestCase {

    static $data = array(
        0 => array(
            'Field1' => 'Value1',
            'Field2' => 'Value2',
        ),
        1 => array(
            'Field1' => 'Value3',
            'Field2' => 'Value4',
        ),
    );

    public function testGetNameAndDimensions() {
        $section = new Section('data', 2);

        $this->assertEquals($section->getName(), 'data');
        $this->assertEquals($section->getDimensions(), 2);
    }

    public function testInjectAndGetRows() {
        $section = new Section('data', 2);
        $rowsRef = &$section->_getRowsReference();
        $rowsRef = SectionTest::$data;

        $getRows = $section->getRows();

        $arrayDiff = array_diff(SectionTest::$data[0], $getRows[0]);
        $this->assertEmpty($arrayDiff);

        $arrayDiff = array_diff(SectionTest::$data[1], $getRows[1]);
        $this->assertEmpty($arrayDiff);
    }

};
