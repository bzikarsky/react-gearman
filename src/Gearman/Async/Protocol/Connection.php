<?php

namespace Gearman\Async\Protocol;

use Evenement\EventEmitter;
use Gearman\Protocol\Binary\CommandFactoryInterface;
use Gearman\Protocol\Binary\CommandInterface;
use Gearman\Protocol\Binary\ReadBuffer;
use Gearman\Protocol\Binary\WriteBuffer;
use Gearman\Protocol\Exception as ProtocolException;
use React\Stream\Stream;
use BadMethodCallException;

/**
 * The connection wraps around the non-async version of the protocol buffers
 * and provides an async interface
 *
 * @event unhandled-command Commands which do not have a registered event-handler are emitted as unhandled-event
 * @event #command-name#    Commands are emitted as events with the command's name as the event-id
 * @event close             When the connection closed "close" is emitted
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>

 */
class Connection extends EventEmitter
{
    /**
     * @var WriteBuffer
     */
    protected $writeBuffer;

    /**
     * @var ReadBuffer
     */
    protected $readBuffer;

    /**
     * @var Stream
     */
    protected $stream;

    /**
     * @var CommandFactoryInterface
     */
    protected $commandFactory;

    /**
     * @var bool
     */
    protected $closed = false;

    /**
     * Creates the connection on top of the async stream and with the given
     * command-factory/specification
     *
     * @param Stream $stream
     * @param CommandFactoryInterface $commandFactory
     */
    public function __construct(Stream $stream, CommandFactoryInterface $commandFactory)
    {
        $this->commandFactory = $commandFactory;
        $this->writeBuffer  = new WriteBuffer();
        $this->readBuffer   = new ReadBuffer($commandFactory);
        $this->stream = $stream;

        // install event-listeners, end event is not of interest
        $this->stream->on('data', function() {
            return call_user_func_array([$this, 'handleData'], func_get_args());
        });
        $this->stream->on('error', function ($error) {
            throw new ProtocolException("Stream-Error: $error");
        });
        $this->stream->on('close', function() {
            $this->closed = true;
            $this->emit('close', [$this]);
        });
    }

    /**
     * Handles incoming data (in byte form) and emits commands when fully read
     *
     * @param string $data
     */
    protected function handleData($data)
    {
        $this->readBuffer->push($data);

        while (count($this->readBuffer)) {
            $command = $this->readBuffer->shift();
            $event = $command->getName();
            $eventArgs = [$command, $this];

            if (count($this->listeners($event))) {
                $this->emit($event, $eventArgs);
            } else {
                $this->emit("unhandled-command", $eventArgs);
            }
        }
    }

    /**
     * Sends a command over the stream
     *
     * @param CommandInterface $command
     * @throws BadMethodCallException when the connection is closed
     */
    public function send(CommandInterface $command)
    {
        if ($this->isClosed()) {
            throw new BadMethodCallException("Connection is closed. Cannot send commands anymore");
        }

        $this->writeBuffer->push($command);
        $this->stream->write($this->writeBuffer->shift());
    }

    /**
     * Returns the command factory
     *
     * @return CommandFactoryInterface
     */
    public function getCommandFactory()
    {
        return $this->commandFactory;
    }

    /**
     * Returns the closed status of the connection
     *
     * @return boolean
     */
    public function isClosed()
    {
        return $this->closed;
    }

    /**
     * Closes the connection
     */
    public function close()
    {
        if (!$this->isClosed()) {
            $this->stream->close();
        }
    }
}
