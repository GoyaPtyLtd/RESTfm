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

class RESTfmMessageTest extends PHPUnit_Framework_TestCase
{
    static $importData = array(
        'meta'  => array(
            0   => array(
                'recordID'  =>  '001',
                'href'      =>  'href1',
            ),
            1   => array(
                'recordID'  =>  '002',
                'href'      =>  'href2',
            ),
        ),
        'data'  => array(
            0   => array(
                'field1'    => 'value1',
                'field2'    => 'value2',
            ),
            1   => array(
                'field1'    => 'value3',
                'field2'    => 'value4',
            ),
        ),
        'info'  => array(
            'infoField1'    => 'infoValue1',
            'infoField2'    => 'infoValue2',
        ),
        'nav'   => array(
            0   => array(
                'navField1' => 'navValue1',
                'navField2' => 'navValue2',
            ),
            1   => array(
                'navField1' => 'navValue3',
                'navField2' => 'navValue4',
            ),
        ),
        'metaField' => array(
            0   => array(
                'metaFieldField1'   => 'metaFieldValue1',
                'metaFieldField2'   => 'metaFieldValue2',
            ),
            1   => array(
                'metaFieldField1'   => 'metaFieldValue3',
                'metaFieldField2'   => 'metaFieldValue4',
            ),
        ),
        'multistatus'   => array(
            0   => array(
                'recordID'  =>  '002',
                'Status'    =>  '101',
                'Reason'    =>  'reason string 1',
            ),
            1   => array(
                'recordID'  =>  '001',
                'Status'    =>  '102',
                'Reason'    =>  'reason string 2',
            ),
        )
    );

    public function testAddAndGetInfo () {
        $message = new RESTfmMessage();

        $message->addInfo('addField1', 'addValue1');
        $message->addInfo('addField2', 'addValue2');

        $getInfo = $message->getInfo();

        $this->assertEquals(
                $getInfo['addField1'],
                'addValue1'
        );

        $this->assertEquals(
                $getInfo['addField2'],
                'addValue2'
        );
    }

    public function testAddAndGetMetaFields () {
        $message = new RESTfmMessage();

        $rowData1 = array(
                        'rowField1' =>  'rowValue1',
                        'rowField2' =>  'rowValue2',
        );
        $rowData2 = array(
                        'rowField1' =>  'rowValue3',
                        'rowField2' =>  'rowValue4',
        );

        $message->addMetaField(new RESTfmMessageRow($rowData1));
        $message->addMetaField(new RESTfmMessageRow($rowData2));

        $metaFields = $message->getMetaFields();

        $arrayDiff1 = array_diff($rowData1, $metaFields[0]->getData());
        $this->assertEmpty($arrayDiff1);

        $arrayDiff2 = array_diff($rowData2, $metaFields[1]->getData());
        $this->assertEmpty($arrayDiff2);
    }

    public function testAddAndGetMultistatus () {
        $message = new RESTfmMessage();

        $message->addMultistatus(new RESTfmMessageMultistatus('1234', 'reason1', 'recordId1'));
        $message->addMultistatus(new RESTfmMessageMultistatus(9999, 'reason2', 'recordId2'));

        $multistatus = $message->getMultistatus();

        $this->assertEquals($multistatus[0]->getStatus(), '1234');
        $this->assertEquals($multistatus[1]->getStatus(), 9999);
    }

    public function testAddAndGetNavs () {
        $message = new RESTfmMessage();

        $rowData1 = array(
                        'rowField1' =>  'rowValue1',
                        'rowField2' =>  'rowValue2',
        );
        $rowData2 = array(
                        'rowField1' =>  'rowValue3',
                        'rowField2' =>  'rowValue4',
        );

        $message->addNav(new RESTfmMessageRow($rowData1));
        $message->addNav(new RESTfmMessageRow($rowData2));

        $metaFields = $message->getNavs();

        $arrayDiff1 = array_diff($rowData1, $metaFields[0]->getData());
        $this->assertEmpty($arrayDiff1);

        $arrayDiff2 = array_diff($rowData2, $metaFields[1]->getData());
        $this->assertEmpty($arrayDiff2);
    }

    public function testAddAndGetRecords () {
        $message = new RESTfmMessage();

        $rowData1 = array(
                        'rowField1' =>  'rowValue1',
                        'rowField2' =>  'rowValue2',
        );
        $rowData2 = array(
                        'rowField1' =>  'rowValue3',
                        'rowField2' =>  'rowValue4',
        );

        $message->addRecord(new RESTfmMessageRecord('001', 'href1', $rowData1));
        $message->addRecord(new RESTfmMessageRecord('002', 'href2', $rowData2));

        $records = $message->getRecords();

        $this->assertEquals($records[0]->getRecordId(), '001');
        $this->assertEquals($records[1]->getRecordId(), '002');

        $arrayDiff1 = array_diff($rowData1, $records[0]->getData());
        $this->assertEmpty($arrayDiff1);

        $arrayDiff2 = array_diff($rowData2, $records[1]->getData());
        $this->assertEmpty($arrayDiff2);
    }

    public function testAddAndGetRecordByRecordId () {
        $message = new RESTfmMessage();

        $rowData1 = array(
                        'rowField1' =>  'rowValue1',
                        'rowField2' =>  'rowValue2',
        );
        $rowData2 = array(
                        'rowField1' =>  'rowValue3',
                        'rowField2' =>  'rowValue4',
        );

        $message->addRecord(new RESTfmMessageRecord('001', 'href1', $rowData1));
        $message->addRecord(new RESTfmMessageRecord('002', 'href2', $rowData2));

        $record = $message->getRecordByRecordId('001');

        $this->assertEquals($record->getRecordId(), '001');
    }

    public function testImportAndExport () {
        $message = new RESTfmMessage();

        $message->importArray(RESTfmMessageTest::$importData);

        $export = $message->exportArray();

        // record 0
        $this->assertEquals(
                RESTfmMessageTest::$importData['meta'][0]['recordID'],
                $export['meta'][0]['recordID']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['meta'][0]['href'],
                $export['meta'][0]['href']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['data'][0]['field1'],
                $export['data'][0]['field1']
        );

        // record 1
        $this->assertEquals(
                RESTfmMessageTest::$importData['meta'][1]['recordID'],
                $export['meta'][1]['recordID']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['meta'][1]['href'],
                $export['meta'][1]['href']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['data'][1]['field1'],
                $export['data'][1]['field1']
        );

        // info
        $this->assertEquals(
                RESTfmMessageTest::$importData['info']['infoField1'],
                $export['info']['infoField1']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['info']['infoField2'],
                $export['info']['infoField2']
        );

        // nav 0
        $this->assertEquals(
                RESTfmMessageTest::$importData['nav'][0]['navField1'],
                $export['nav'][0]['navField1']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['nav'][0]['navField2'],
                $export['nav'][0]['navField2']
        );

        // nav 1
        $this->assertEquals(
                RESTfmMessageTest::$importData['nav'][1]['navField1'],
                $export['nav'][1]['navField1']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['nav'][1]['navField2'],
                $export['nav'][1]['navField2']
        );

        // metaField 0
        $this->assertEquals(
                RESTfmMessageTest::$importData['metaField'][0]['metaFieldField1'],
                $export['metaField'][0]['metaFieldField1']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['metaField'][0]['metaFieldField2'],
                $export['metaField'][0]['metaFieldField2']
        );

        // metaField 1
        $this->assertEquals(
                RESTfmMessageTest::$importData['metaField'][1]['metaFieldField1'],
                $export['metaField'][1]['metaFieldField1']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['metaField'][1]['metaFieldField2'],
                $export['metaField'][1]['metaFieldField2']
        );

        // multistatus 0
        $this->assertEquals(
                RESTfmMessageTest::$importData['multistatus'][0]['recordID'],
                $export['multistatus'][0]['recordID']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['multistatus'][0]['Status'],
                $export['multistatus'][0]['Status']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['multistatus'][0]['Reason'],
                $export['multistatus'][0]['Reason']
        );

        // multistatus 1
        $this->assertEquals(
                RESTfmMessageTest::$importData['multistatus'][1]['recordID'],
                $export['multistatus'][1]['recordID']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['multistatus'][1]['Status'],
                $export['multistatus'][1]['Status']
        );
        $this->assertEquals(
                RESTfmMessageTest::$importData['multistatus'][1]['Reason'],
                $export['multistatus'][1]['Reason']
        );
    }


};
