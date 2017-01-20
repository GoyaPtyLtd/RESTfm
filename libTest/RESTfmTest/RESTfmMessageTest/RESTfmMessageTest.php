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

    public function testAddAndGetInfo () {
        $message = new RESTfmMessage();

        $message->addInfo('addField0', 'addValue0');
        $message->addInfo('addField1', 'addValue1');

        $getInfo = $message->getInfo();

        $this->assertEquals(
                $getInfo['addField0'],
                'addValue0'
        );

        $this->assertEquals(
                $getInfo['addField1'],
                'addValue1'
        );
    }

    public function testSetAndGetMetaFields () {
        $message = new RESTfmMessage();

        // We only need to identify objects here, not the object's data.
        // The object's own test files are the only place data is tested.

        $messageRow0 = new RESTfmMessageRow();
        $messageRow1 = new RESTfmMessageRow();

        $message->setMetaField('name0', $messageRow0);
        $message->setMetaField('name1', $messageRow1);

        $this->assertNull($message->getMetaField('nonExistent'));

        $getMessageRow0 = $message->getMetaField('name0');
        $getMessageRow1 = $message->getMetaField('name1');

        $this->assertEquals( spl_object_hash($getMessageRow0),
                             spl_object_hash($messageRow0) );

        $this->assertEquals( spl_object_hash($getMessageRow1),
                             spl_object_hash($messageRow1) );

        $metaFields = $message->getMetaFields();

        $this->assertEquals( spl_object_hash($metaFields['name0']),
                             spl_object_hash($messageRow0) );

        $this->assertEquals( spl_object_hash($metaFields['name1']),
                             spl_object_hash($messageRow1) );
    }

    public function testAddAndGetMultistatus () {
        $message = new RESTfmMessage();

        // We only need to identify objects here, not the object's data.
        // The object's own test files are the only place data is tested.

        $messageMultistatus0 = new RESTfmMessageMultistatus();
        $messageMultistatus1 = new RESTfmMessageMultistatus();

        $message->addMultistatus($messageMultistatus0);
        $message->addMultistatus($messageMultistatus1);

        $multistatus = $message->getMultistatus();

        $this->assertEquals( spl_object_hash($multistatus[0]),
                             spl_object_hash($messageMultistatus0) );

        $this->assertEquals( spl_object_hash($multistatus[1]),
                             spl_object_hash($messageMultistatus1) );
    }

    public function testAddAndGetNavs () {
        $message = new RESTfmMessage();

        // We only need to identify objects here, not the object's data.
        // The object's own test files are the only place data is tested.

        $messageRow0 = new RESTfmMessageRow();
        $messageRow1 = new RESTfmMessageRow();

        $message->addNav($messageRow0);
        $message->addNav($messageRow1);

        $navs = $message->getNavs();

        $this->assertEquals( spl_object_hash($navs[0]),
                             spl_object_hash($messageRow0) );

        $this->assertEquals( spl_object_hash($navs[1]),
                             spl_object_hash($messageRow1) );
    }

    public function testAddAndGetRecords () {
        $message = new RESTfmMessage();

        // We only need to identify objects here, not the object's data.
        // The object's own test files are the only place data is tested.

        $rowData1 = array(
                        'rowField1' =>  'rowValue1',
                        'rowField2' =>  'rowValue2',
        );
        $rowData2 = array(
                        'rowField1' =>  'rowValue3',
                        'rowField2' =>  'rowValue4',
        );

        $messageRecord0 = new RESTfmMessageRecord();
        $messageRecord1 = new RESTfmMessageRecord();

        $message->addRecord($messageRecord0);
        $message->addRecord($messageRecord1);

        $records = $message->getRecords();

        $this->assertEquals( spl_object_hash($records[0]),
                             spl_object_hash($messageRecord0) );

        $this->assertEquals( spl_object_hash($records[1]),
                             spl_object_hash($messageRecord1) );
    }

    public function testAddAndGetRecordByRecordId () {
        $message = new RESTfmMessage();

        // We only need to identify objects here, not the object's data.
        // The object's own test files are the only place data is tested.

        $messageRecord001 = new RESTfmMessageRecord('001');
        $messageRecord002 = new RESTfmMessageRecord('002');

        $message->addRecord($messageRecord001);
        $message->addRecord($messageRecord002);

        $record = $message->getRecordByRecordId('001');

        $this->assertEquals( spl_object_hash($record),
                             spl_object_hash($messageRecord001) );

        $this->assertNull($message->getRecordByRecordId('nonExistent'));
    }

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
                'name'              => 'name0',
                'metaFieldField1'   => 'metaFieldValue1',
                'metaFieldField2'   => 'metaFieldValue2',
            ),
            1   => array(
                'name'              => 'name1',
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

    public function testGetSectionNames () {
        $message = new RESTfmMessage();

        $this->assertEmpty($message->getSectionNames());

        $message->importArray(RESTfmMessageTest::$importData);

        $sectionNames = $message->getSectionNames();

        $this->assertArraySubset(['meta', 'data', 'info', 'metaField',
                                  'multistatus', 'nav'],
                                 $sectionNames);
    }

    public function testNonExistentGetSection () {
        $message = new RESTfmMessage();

        $this->assertNull($message->getSection('nonExistent'));
    }

    /**
     * As setSection() must create a new record, or reuse an existing
     * record between adding 'data' and 'meta' sections, we need to ensure
     * that it handles this correctly in both orders.
     */
    public function testDataMetaOrderInSetSection () {
        $meta = array(
            0   => array(
                'recordID'  =>  '001',
                'href'      =>  'href1',
            ),
            1   => array(
                'recordID'  =>  '002',
                'href'      =>  'href2',
            ),
        );
        $data = array(
            0   => array(
                'field1'    => 'value1',
                'field2'    => 'value2',
            ),
            1   => array(
                'field1'    => 'value3',
                'field2'    => 'value4',
            ),
        );

        $messageMetaBeforeData = new RESTfmMessage();
        $messageMetaBeforeData->setSection('meta', $meta);
        $messageMetaBeforeData->setSection('data', $data);

        $messageDataBeforeMeta = new RESTfmMessage();
        $messageDataBeforeMeta->setSection('data', $data);
        $messageDataBeforeMeta->setSection('meta', $meta);

        $exportMetaBeforeData = $messageMetaBeforeData->exportArray();
        $exportDataBeforeMeta = $messageDataBeforeMeta->exportArray();

        $this->assertNotEmpty($exportMetaBeforeData);

        $arrayDiff = array_diff($exportMetaBeforeData['meta'][0], $exportDataBeforeMeta['meta'][0]);
        $this->assertEmpty($arrayDiff);

        $arrayDiff = array_diff($exportMetaBeforeData['data'][1], $exportDataBeforeMeta['data'][1]);
        $this->assertEmpty($arrayDiff);
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

    public function testToString () {
        $message = new RESTfmMessage();

        $message->importArray(RESTfmMessageTest::$importData);

        $this->assertNotEmpty($message->__toString());
    }

};
