<?php

use Zikarsky\React\Gearman\Command\Binary\CommandType;
use Zikarsky\React\Gearman\Command\Binary\Command;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandFactory;
use Zikarsky\React\Gearman\Protocol\Connection;

class ConnectionTest extends PHPUnit_Framework_TestCase
{
    protected $type;
    protected $packet;
    protected $packetStr;

    protected $stream;
    protected $connection;

    public function setUp()
    {
        $this->type = new CommandType("TEST", 1, ["a", "b"]);

        $fac = new CommandFactory();
        $fac->addType($this->type);

        $this->stream = $this->getMockBuilder(\React\Stream\Stream::class)
            ->setMethods(['write', 'close', 'getBuffer'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->connection = new Connection($this->stream, $fac);
        $this->packet = $fac->create(1, ["a" => "foo", "b" => 1]);

        $buf = new \Zikarsky\React\Gearman\Command\Binary\WriteBuffer();
        $buf->push($this->packet);
        $this->packetStr = $buf->shift(null);
    }

    /**
     * @expectedException \Zikarsky\React\Gearman\Command\Exception
     */
    public function testStreamError()
    {
        $this->stream->emit("error", ["test-error"]);
    }

    public function testStreamClose()
    {
        $closeCalled = false;
        $this->connection->on("close", function () use (&$closeCalled) {
            $closeCalled = true;
        });

        $this->stream->emit("close");
        $this->assertTrue($closeCalled);
        $this->assertTrue($this->connection->isClosed());

        return $this->connection;
    }

    /**
     * @depends testStreamClose
     * @expectedException BadMethodCallException
     */
    public function testSendFailOnClosedConnection(Connection $connection)
    {
        $connection->send($this->packet);
    }

    public function testHandledPacketEvent()
    {
        $testCalled = false;
        $this->connection->on("TEST", function ($event) use (&$testCalled) {
            $this->assertEquals($this->packet, $event);
            $testCalled = true;
        });

        $this->stream->emit('data', [$this->packetStr]);
        $this->assertTrue($testCalled);
    }

    public function testUnhandledPacketEvent()
    {
        $testCalled = false;
        $this->connection->on("unhandled-command", function ($event) use (&$testCalled) {
            $this->assertEquals($this->packet, $event);
            $testCalled = true;
        });

        $this->stream->emit('data', [$this->packetStr]);
        $this->assertTrue($testCalled);
    }

    public function testWrite()
    {
        $this->stream->expects($this->once())
            ->method('write')
            ->with($this->equalTo($this->packetStr))
        ;
        $buffer = $this->getMockBuilder(\React\Stream\Buffer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $buffer->expects($this->once())
            ->method('on')
            ->with($this->equalTo('full-drain'));
        $this->stream->expects($this->once())
            ->method('getBuffer')
            ->willReturn($buffer)
        ;

        $this->connection->send($this->packet);
    }

    public function testClose()
    {
        $this->stream->expects($this->once())
            ->method('close');

        $this->connection->close();
    }

}
