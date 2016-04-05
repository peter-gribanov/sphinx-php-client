<?php
/**
 * $Id$
 */

/**
 * Copyright (c) 2001-2015, Andrew Aksyonoff
 * Copyright (c) 2008-2015, Sphinx Technologies Inc
 * All rights reserved
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Library General Public License. You should
 * have received a copy of the LGPL license along with this program; if you
 * did not, you can find it at http://www.gnu.org/
 */

namespace Sphinx\Tests;

use Sphinx\Client;

/**
 * Class SphinxClientTest
 */
class SphinxClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $sphinx;

    protected function setUp()
    {
        $this->sphinx = new Client();
    }

    public function testGetLastErrorNoError()
    {
        $this->assertEmpty($this->sphinx->getLastError());
    }

    public function testGetLastWarningNoWarning()
    {
        $this->assertEmpty($this->sphinx->getLastWarning());
    }

    public function testIsConnectErrorNoError()
    {
        $this->assertFalse($this->sphinx->isConnectError());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSetWeights()
    {
        $this->sphinx->setWeights();
    }
}
