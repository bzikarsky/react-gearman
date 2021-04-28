<?php

namespace Zikarsky\React\Gearman\Protocol;

use Evenement\EventEmitter;
use Exception;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandFactoryInterface;
use Zikarsky\React\Gearman\Command\Exception as ProtocolException;
use React\Promise\Promise;
use React\Promise\Deferred;
use Zikarsky\React\Gearman\ConnectionLostException;
use function React\Promise\resolve;

/**
 * A participant is a async participant in the Gearman protocol, such as Clients, Workers and Servers
 * This abstract class provides some basic methods, which allow an easier handling of the more ordered/blocking
 * parts of the protocol
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
abstract class Participant extends EventEmitter
{
    private Connection $connection;

    /**
     * Queued requests to be sent, requests are enqueued if send is currently locked
     */
    protected array $sendQueue = [];

    /**
     * If send is currently locked
     */
    private bool $sendIsLocked = false;

    /**
     * Creates an Participant and registers handlers for packets not handled by the actual particpant and
     * ERROR packets
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        $this->connection->on('unhandled-command', function (CommandInterface $command) {
            throw new ProtocolException("Unexpected command packet: $command");
        });
        $this->connection->on("ERROR", function (CommandInterface $command) {
            throw new ProtocolException("Protocol error: " . $command->get('message') . '(' . $command->get('code') . ')');
        });
        $this->connection->on("close", function () {
            $this->emit("close", func_get_args());
        });
    }

    /**
     * Pings the server with random data, and expects a pong with the same information
     * The returned promise is resolved with TRUE as soon the pong request hits the client
     */
    public function ping(): PromiseInterface
    {
        $command = $this->getCommandFactory()->create("ECHO_REQ", [
            CommandInterface::DATA => uniqid('', true)
        ]);

        return $this->blockingAction($command, 'ECHO_RES', function (CommandInterface $ping, CommandInterface $pong) {
            $success = $ping->get(CommandInterface::DATA) === $pong->get(CommandInterface::DATA);
            if (!$success) {
                throw new ProtocolException("Ping response did not match ping request");
            }

            $this->emit("ping", [$this]);

            return true;
        });
    }

    protected function blockingActionStart(): void
    {
    }

    protected function blockingActionEnd(): void
    {
    }

    /**
     * Performs a blocking action (request<->response pattern)
     *
     * This action sends the given command, and executes the handler upon receiving the defined event-name
     * All other sent commands are queued until the handler resolves the initial action-promise
     */
    protected function blockingAction(CommandInterface $command, string $eventName, callable $handler): PromiseInterface
    {
        // create the deferred action to execute $handler on $eventName after sending $command
        $deferred = new Deferred();
        $this->blockingActionStart();

        $failListener = function () use ($deferred) {
            $deferred->reject(new ConnectionLostException());
        };

        $successListener = function (CommandInterface $recvCommand) use ($command, $deferred, $handler) {
            // if the result is not NULL resolve the deferred action with the handler's result
            // if the result is NULL we assume the handler communicated the result on the passed in deferred
            // itself
            $result = $handler($command, $recvCommand, $deferred);
            if ($result !== null) {
                $this->blockingActionEnd();
                $deferred->resolve($result);
            }
        };

        $deferred->promise()->always(function () use ($eventName, $successListener, $failListener) {
            $this->connection->removeListener($eventName, $successListener);
            $this->connection->removeListener('close', $failListener);
        });

        // send command
        $promise = $deferred->promise();
        $this->sendDeferred($command, $promise)->then(
            function () use ($eventName, $successListener, $failListener) {
                $this->connection->once($eventName, $successListener);
                $this->connection->once('close', $failListener);
            },
            fn ($e) => $deferred->reject($e)
        );
        return $deferred->promise();
    }

    /**
     * Sends a command and returns a promise which is resolved as soon the command is sent
     *
     * A lock-promise can be passed in optionally. All subsequent calls to send() are then queued until the
     * promise resolves
     */
    protected function send(CommandInterface $command): PromiseInterface
    {
        return $this->sendDeferred($command);
    }

    /**
     * INTERNAL ONLY: Sends the command and resolves the the passed in deferred as soon
     * the command is really sent.
     * Other commands are queued until an optional promise resolves (unlocks)
     */
    private function sendDeferred(CommandInterface $command, ?Promise $lockedUntil = null): PromiseInterface
    {
        // If sending is not locked and we do not have to unlock, just send
        if ($this->sendIsLocked === false && $lockedUntil === null) {
            $this->connection->send($command);
            return resolve();
        }

        // We are locked: Enqueue send and return promise
        if ($this->sendIsLocked === true) {
            $deferred = new Deferred();
            $this->sendQueue[] = [$command, $lockedUntil, $deferred];
            return $deferred->promise();
        }

        // if this operation is blocking:
        //  - lock down sending
        //  - install a resolve-handler which removes the lock
        //  - install an error-handler to communicate the failure
        $this->sendIsLocked = true;

        // Define unlock-handler which resumes sending
        $onUnlock = function () {
            $this->sendIsLocked = false;

            if (empty($this->sendQueue)) {
                return;
            }

            $oldQueue = $this->sendQueue;
            $this->sendQueue= [];
            foreach ($oldQueue as [$command, $lock, $deferred]) {
                $this->sendDeferred($command, $lock)
                    ->then(fn () => $deferred->resolve())
                    ->otherwise(fn ($e) => $deferred->reject($e))
                ;
            }
        };

        // Also unlock if previous command failed, otherwise there is a deadlock when issueing a blocked command after a failure
        $lockedUntil->then($onUnlock, $onUnlock);

        $this->connection->send($command);
        return resolve();
    }

    /**
     * Returns the connection
     */
    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Returns the command-factory
     */
    protected function getCommandFactory(): CommandFactoryInterface
    {
        return $this->connection->getCommandFactory();
    }

    public function disconnect(bool $graceful = false): void
    {
        if ($graceful) {
            $this->connection->end();
        } else {
            $this->connection->close();
        }
    }
}
