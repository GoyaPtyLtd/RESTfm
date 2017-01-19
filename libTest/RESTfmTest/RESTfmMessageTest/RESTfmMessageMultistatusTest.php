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

class RESTfmMessageMultistatusTest extends PHPUnit_Framework_TestCase {

    public function testConstructorSetAndGet() {
        $multistatus = new RESTfmMessageMultistatus('9999', 'test again', 'someOtherRecordId');

        $this->assertEquals($multistatus->getStatus(), '9999');
        $this->assertEquals($multistatus->getReason(), 'test again');
        $this->assertEquals($multistatus->getRecordId(), 'someOtherRecordId');
    }

    public function testSetAndGetStatus() {
        $multistatus = new RESTfmMessageMultistatus();

        $this->assertNull($multistatus->getStatus());

        $multistatus->setStatus(1234);

        $this->assertEquals($multistatus->getStatus(), 1234);
    }

    public function testSetAndGetReason() {
        $multistatus = new RESTfmMessageMultistatus();

        $this->assertNull($multistatus->getReason());

        $multistatus->setReason('test reason');

        $this->assertEquals($multistatus->getReason(), 'test reason');
    }

    public function testSetAndGetRecordId() {
        $multistatus = new RESTfmMessageMultistatus();

        $this->assertNull($multistatus->getRecordId());

        $multistatus->setRecordId('someRecordId');

        $this->assertEquals($multistatus->getRecordId(), 'someRecordId');
    }

    public function testGetMultistatusReference() {
        $multistatus = new RESTfmMessageMultistatus('9999', 'test again', 'someOtherRecordId');
        $multistatusReference = &$multistatus->_getMultistatusReference();

        $this->assertEquals($multistatusReference['Status'], '9999');
        $this->assertEquals($multistatusReference['Reason'], 'test again');
        $this->assertEquals($multistatusReference['recordID'], 'someOtherRecordId');
    }

};
