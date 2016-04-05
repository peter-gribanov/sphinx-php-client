<?php

/**
 * Class SphinxClientTest
 */
class SphinxClientTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SphinxClient
     */
    protected $sphinx;

    protected function setUp()
    {
        $this->sphinx = new SphinxClient();
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
