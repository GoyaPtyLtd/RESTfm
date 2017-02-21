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

namespace RESTfmTests;

class VersionTest extends \PHPUnit_Framework_TestCase {

    static $versionPath = "lib/RESTfm/Version.php";

    public function testGetters () {
        // We explicitly include Version.php here so we can capture and
        // discard the output of the inline code (outputs Version on CLI).
        ob_start();
        include VersionTest::$versionPath;
        ob_end_clean();

        // Protocol is always an integer.
        $this->assertRegExp('/^\d+$/', \Version::getProtocol());

        // Revision and release could be any non empty string.
        $this->assertNotEmpty(\Version::getRelease());
        $this->assertNotEmpty(\Version::getRevision());

        // Versions always contains a slash.
        $this->assertRegExp('/\//', \Version::getVersion());
    }

    /**
     * @depends testGetters
     */
    public function testCliProtocol () {
        $this->assertEquals(
            exec('php ' . VersionTest::$versionPath . ' -p'),
            \Version::getProtocol()
        );
    }

    /**
     * @depends testGetters
     */
    public function testCliRelease () {
        // This doesn't perform any code coverage.
        $this->assertEquals(
            exec('php ' . VersionTest::$versionPath . ' -r'),
            \Version::getRelease()
        );
    }

    /**
     * @depends testGetters
     */
    public function testCliVersion () {
        // This doesn't perform any code coverage.
        $this->assertEquals(
            exec('php ' . VersionTest::$versionPath),
            \Version::getVersion()
        );
    }
};
