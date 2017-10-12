<?php

namespace Zikarsky\React\Gearman\Protocol;

use Evenement\EventEmitter;
use React\Promise\RejectedPromise;
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
    public $stream;

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
     * @param Stream                  $stream
     * @param CommandFactoryInterface $commandFactory
     */
    public function __construct(Stream $stream, CommandFactoryInterface $commandFactory)
    {
        $this->commandFactory = $commandFactory;
        $this->writeBuffer  = new WriteBuffer();
        $this->readBuffer   = new ReadBuffer($commandFactory);
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
        $this->stream->getBuffer()->on('full-drain', function () {
            foreach ($this->commandSendQueue as $deferred) {
                $deferred->resolve();
            }
            $this->commandSendQueue = [];
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
     *
     * @param  CommandInterface       $command
     * @throws BadMethodCallException when the connection is closed
     * @return \React\Promise\Promise|\React\Promise\PromiseInterface
     */
    public function send(CommandInterface $command)
    {
        if ($this->isClosed()) {
            return new RejectedPromise(new BadMethodCallException("Connection is closed. Cannot send commands anymore"));
        }

        $deferred = new Deferred();
        $this->logger->info("> $command");
        $this->writeBuffer->push($command);
        $this->stream->write($this->writeBuffer->shift());
        $this->commandSendQueue[] = $deferred;

        return $deferred->promise();
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
