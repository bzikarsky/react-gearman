<?php

namespace Zikarsky\React\Gearman\Protocol;

use Evenement\EventEmitter;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use React\Stream\DuplexStreamInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandFactoryInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\ReadBuffer;
use Zikarsky\React\Gearman\Command\Binary\WriteBuffer;
use Zikarsky\React\Gearman\Command\Exception as ProtocolException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Stream\Stream;
use React\Promise\Deferred;
use BadMethodCallException;
use Zikarsky\React\Gearman\ConnectionLostException;
use function React\Promise\reject;

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
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * @var Deferred[]
     */
    protected $commandSendQueue = [];

    /**
     * Creates the connection on top of the async stream and with the given
     * command-factory/specification
     *
     * @param DuplexStreamInterface $stream
     * @param CommandFactoryInterface $commandFactory
     */
    public function __construct(DuplexStreamInterface $stream, CommandFactoryInterface $commandFactory)
    {
        $this->commandFactory = $commandFactory;
        $this->writeBuffer = new WriteBuffer();
        $this->readBuffer = new ReadBuffer($commandFactory);
        $this->stream = $stream;
        $this->logger = new NullLogger();

        // install event-listeners, end event is not of interest
        $this->stream->on('data', function () {
            return call_user_func_array([$this, 'handleData'], func_get_args());
        });
        $this->stream->on('error', function ($error) {
            throw new ProtocolException("Stream-Error: $error");
        });
        $this->stream->on('close', function () {
            $this->closed = true;
            $this->emit('close', [$this]);
        });

        $this->on('close', function () {
            foreach ($this->commandSendQueue as $deferred) {
                $deferred->reject(new ConnectionLostException());
            }
        });
    }

    /**
     * Sets a protocol logger
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function pause()
    {
        $this->stream->pause();
    }

    public function resume()
    {
        $this->stream->resume();
    }

    /**
     * Handles incoming data (in byte form) and emits commands when fully read
     */
    protected function handleData(string $data): void
    {
        $this->readBuffer->push($data);

        while (count($this->readBuffer)) {
            $command = $this->readBuffer->shift();
            $this->logger->info("< $command");

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
     * @throws BadMethodCallException when the connection is closed
     */
    public function send(CommandInterface $command): void
    {
        if ($this->isClosed()) {
            throw new BadMethodCallException("Connection is closed. Cannot send commands anymore");
        }

        $this->logger->info("> $command");
        $this->writeBuffer->push($command);
        $this->stream->write($this->writeBuffer->shift());
    }

    /**
     * Returns the command factory
     */
    public function getCommandFactory(): CommandFactoryInterface
    {
        return $this->commandFactory;
    }

    /**
     * Returns the closed status of the connection
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Closes the connection
     */
    public function close(): void
    {
        if (!$this->isClosed()) {
            $this->stream->close();
        }
    }
}
