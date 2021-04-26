<?php

use Zikarsky\React\Gearman\Command\Binary\CommandFactory;
use Zikarsky\React\Gearman\Command\Binary\Command;
use Zikarsky\React\Gearman\Command\Binary\CommandType;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\ReadBuffer;

class ReadBufferTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var ReadBuffer;
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

    public function setUp(): void    {
        $this->fac = new CommandFactory();

        $this->typeA = new CommandType("TEST_A", 1, []);
        $this->typeB = new CommandType("TEST_B", 2, ["a", "b"]);

        $this->fac->addType($this->typeA);
        $this->fac->addType($this->typeB);

        $this->buf = new ReadBuffer($this->fac);

        $this->reqPacket = new Command($this->typeA, [], CommandInterface::MAGIC_REQUEST);
        $this->resPacket = new Command($this->typeB, ["a" => "foo", "b" => "bar"], CommandInterface::MAGIC_RESPONSE);
    }

    public function testPushInvalidData()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->buf->push(1);
    }

    public function testShiftOnEmptyBuffer()
    {
        $this->expectException(OutOfBoundsException::class);
        $this->buf->shift();
    }

    public function testCount()
    {
        $this->assertEquals(0, count($this->buf));
        $this->buf->push($this->reqPacketStr);
        $this->assertEquals(1, count($this->buf));
    }

    public function testSinglePacketPush()
    {
        $result = $this->buf->push($this->reqPacketStr);
        $this->assertEquals(1, $result);

        $this->assertEquals($this->reqPacket, $this->buf->shift());
    }

    public function testPartialPacketPush()
    {
        $count = strlen($this->reqPacketStr);
        for ($i = 0; $i < $count; $i++) {
            $result = $this->buf->push($this->reqPacketStr[$i]);

            if ($i < $count-1) {
                $this->assertEquals(0, $result);
            } else {
                $this->assertEquals(1, $result);
            }
        }

        $this->assertEquals($this->reqPacket, $this->buf->shift());
    }

    public function testMultiPacketPush()
    {
        $buf = $this->reqPacketStr
             . $this->resPacketStr
             . $this->resPacketStr
             . $this->reqPacketStr
             . $this->resPacketStr
        ;

        $cmds = [
            $this->reqPacket,
            $this->resPacket,
            $this->resPacket,
            $this->reqPacket,
            $this->resPacket
        ];

        $this->assertEquals(5, $this->buf->push($buf));

        foreach ($cmds as $idx => $exp) {
            $real = $this->buf->shift();
            $this->assertEquals($exp, $real, "Packets at position $idx are not equal: $real != $exp");
        }
    }

    public function testBrokenPacket()
    {
        $this->expectException(\Zikarsky\React\Gearman\Command\Exception::class);
        $this->buf->push($this->brokenPacketStr);
    }
}
