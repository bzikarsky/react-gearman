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
        $this->setUpStream();
    }

    protected function setUpStream()
    {
        $this->type = new CommandType("TEST", 1, ["a", "b"]);

        $fac = new CommandFactory();
        $fac->addType($this->type);

        $this->stream = $this->getMockBuilder(\React\Stream\Stream::class)
            ->setMethods(['write', 'close', 'getBuffer'])
            ->disableOriginalConstructor()
            ->getMock();
        $buffer = $this->createMock(\React\Stream\Buffer::class);
        $this->stream->expects($this->any())->method('getBuffer')->willReturn($buffer);
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
     */
    public function testSendFailOnClosedConnection(Connection $connection)
    {
        $thrown = null;
        $connection->send($this->packet)->otherwise(function ($e) use (&$thrown) {
            $thrown = $e;
        });

        $this->assertInstanceOf(BadMethodCallException::class, $thrown);
    }

    /**
     * @depends testSendFailOnClosedConnection
     */
    public function testSendFailsWhenConnectionIsClosedDuringSend()
    {
        // Need to re-establish connection
        $this->setUpStream();
        $thrown = null;
        $this->connection->send($this->packet)->otherwise(function ($e) use (&$thrown) {
            $thrown = $e;
        });
        $this->stream->emit('close');

        $this->assertTrue($this->connection->isClosed());
        $this->assertInstanceOf(\Zikarsky\React\Gearman\ConnectionLostException::class, $thrown);
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
            ->with($this->equalTo($this->packetStr));

        $this->connection->send($this->packet);
    }

    public function testClose()
    {
        $this->stream->expects($this->once())
            ->method('close');

        $this->connection->close();
    }
}
