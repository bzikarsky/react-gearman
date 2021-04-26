<?php

use Zikarsky\React\Gearman\Command\Binary\CommandType;
use Zikarsky\React\Gearman\Command\Binary\Command;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandFactory;
use Zikarsky\React\Gearman\Protocol\Connection;

class ConnectionTest extends \PHPUnit\Framework\TestCase
{
    protected $type;
    protected $packet;
    protected $packetStr;

    protected $stream;
    protected Connection $connection;

    public function setUp(): void
    {
        $this->setUpStream();
    }

    protected function setUpStream()
    {
        $this->type = new CommandType("TEST", 1, ["a", "b"]);

        $fac = new CommandFactory();
        $fac->addType($this->type);

        $this->stream = new \React\Stream\CompositeStream(
            $this->createMock(\React\Stream\ReadableStreamInterface::class),
            $this->createMock(\React\Stream\WritableStreamInterface::class)
        );

        $this->connection = new Connection($this->stream, $fac);
        $this->packet = $fac->create(1, ["a" => "foo", "b" => 1]);

        $buf = new \Zikarsky\React\Gearman\Command\Binary\WriteBuffer();
        $buf->push($this->packet);
        $this->packetStr = $buf->shift(null);
    }

    public function testStreamError()
    {
        $this->expectException(\Zikarsky\React\Gearman\Command\Exception::class);
        $this->stream->emit("error", ["test-error"]);
    }

    public function testStreamClose()
    {
        $closeCalled = false;
        $this->connection->on("close", function () use (&$closeCalled) {
            $closeCalled = true;
        });

        $this->stream->emit("close");
        self::assertTrue($closeCalled);
        self::assertTrue($this->connection->isClosed());

        return $this->connection;
    }

    /**
     * @depends testStreamClose
     */
    public function testSendFailOnClosedConnection(Connection $connection)
    {
        $this->expectException(BadMethodCallException::class);
        $thrown = null;
        $connection->send($this->packet)->otherwise(function ($e) use (&$thrown) {
            $thrown = $e;
        });
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
}
