<?php

use Gearman\Protocol\Binary\CommandType;
use Gearman\Protocol\Binary\Command;
use Gearman\Protocol\Binary\CommandInterface;
use Gearman\Protocol\Binary\CommandFactory;
use Gearman\Async\Protocol\Connection;

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

        $this->stream = $this->getMock('\React\Stream\Stream', ['write', 'close'], [], '', false);
        $this->connection = new Connection($this->stream, $fac);
        $this->packet = $fac->create(1, ["a" => "foo", "b" => 1]);

        $buf = new \Gearman\Protocol\Binary\WriteBuffer();
        $buf->push($this->packet);
        $this->packetStr = $buf->shift(null);
    }

    /**
     * @expectedException \Gearman\Protocol\Exception
     */
    public function testStreamError()
    {
        $this->stream->emit("error", ["test-error"]);
    }

    public function testStreamClose()
    {
        $closeCalled = false;
        $this->connection->on("close", function() use (&$closeCalled) {
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
        $this->connection->on("TEST", function($event) use (&$testCalled) {
            $this->assertEquals($this->packet, $event);
            $testCalled = true;
        });

        $this->stream->emit('data', [$this->packetStr]);
        $this->assertTrue($testCalled);
    }

    public function testUnhandledPacketEvent()
    {
        $testCalled = false;
        $this->connection->on("unhandled-command", function($event) use (&$testCalled) {
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

        $this->connection->send($this->packet);
    }

    public function testClose()
    {
        $this->stream->expects($this->once())
            ->method('close');

        $this->connection->close();
    }

}
