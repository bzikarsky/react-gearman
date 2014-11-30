<?php

use Zikarsky\React\Gearman\Command\Binary\CommandFactory;
use Zikarsky\React\Gearman\Command\Binary\Command;
use Zikarsky\React\Gearman\Command\Binary\CommandType;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\WriteBuffer;

class WriteBufferTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var WriteBuffer;
     */
    protected $buf;
    protected $fac;

    protected $typeA;
    protected $typeB;

    protected $reqPacketStr = "\0REQ\0\0\0\1\0\0\0\0";
    protected $resPacketStr = "\0RES\0\0\0\2\0\0\0\7foo\0bar";

    protected $brokenPacketStr = "\0RES\0\0\0\2\0\0\0\7fooXbar";

    protected $reqPacket;
    protected $resPacket;

    public function setUp()
    {
        $this->fac = new CommandFactory();

        $this->typeA = new CommandType("TEST_A", 1, []);
        $this->typeB = new CommandType("TEST_B", 2, ["a", "b"]);

        $this->fac->addType($this->typeA);
        $this->fac->addType($this->typeB);

        $this->buf = new WriteBuffer($this->fac);

        $this->reqPacket = new Command($this->typeA, [], CommandInterface::MAGIC_REQUEST);
        $this->resPacket = new Command($this->typeB, ["a" => "foo", "b" => "bar"], CommandInterface::MAGIC_RESPONSE);
    }

    public function testSingleCommand()
    {
        $result = $this->buf->push($this->resPacket);
        $this->assertEquals(strlen($this->resPacketStr), $result);
        $this->assertEquals($this->resPacketStr, $this->buf->shift());
    }

    public function testMultiCommand()
    {
        $result = $this->buf->push($this->reqPacket);
        $this->assertEquals(strlen($this->reqPacketStr), $result);

        $result = $this->buf->push($this->resPacket);
        $this->assertEquals(strlen($this->reqPacketStr . $this->resPacketStr), $result);

        $this->assertEquals($this->reqPacketStr . $this->resPacketStr, $this->buf->shift());
    }

    public function testPartialShift()
    {
        $this->buf->push($this->reqPacket);
        $this->assertEquals($this->reqPacketStr[0], $this->buf->shift(1));
        $this->assertEquals(substr($this->reqPacketStr, 1), $this->buf->shift());
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testInvalidShiftOnEmptyBuffer()
    {
        $this->buf->shift(1);
    }

    public function testShiftOnEmptyBuf()
    {
        $this->assertEquals("", $this->buf->shift(null));
        $this->assertEquals("", $this->buf->shift());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidShiftWithZero()
    {
        $this->buf->shift(0);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidShiftWithNegative()
    {
        $this->buf->shift(-1);
    }

}
