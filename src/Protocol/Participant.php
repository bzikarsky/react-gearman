<?php

namespace Zikarsky\React\Gearman\Protocol;

use Evenement\EventEmitter;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandFactoryInterface;
use Zikarsky\React\Gearman\Command\Exception as ProtocolException;
use React\Promise\Promise;
use React\Promise\Deferred;

/**
 * A participant is a async participant in the Gearman protocol, such as Clients, Workers and Servers
 * This abstract class provides some basic methods, which allow an easier handling of the more ordered/blocking
 * parts of the protocol
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
abstract class Participant extends EventEmitter
{

    /**
     * @var Connection
     */
    private $connection;

    /**
     * Queued requests to be sent, requests are enqueued if send is currently locked
     *
     * @var array
     */
    protected $sendQueue = [];

    /**
     * If send is currently locked
     *
     * @var bool
     */
    protected $sendLocked = false;

    /**
     * Creates an Participant and registers handlers for packets not handled by the actual particpant and
     * ERROR packets
     *
     * @param Connection $connection
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
     *
     * @return Promise
     */
    public function ping()
    {
        $command = $this->getCommandFactory()->create("ECHO_REQ", [
            CommandInterface::DATA => uniqid()
        ]);

        return $this->blockingAction($command, 'ECHO_RES', function (CommandInterface $ping, CommandInterface $pong) {
            $success = $ping->get(CommandInterface::DATA) == $pong->get(CommandInterface::DATA);
            if (!$success) {
                throw new ProtocolException("Ping response did not match ping request");
            }

            $this->emit("ping", [$this]);

            return true;
        });
    }

    protected function blockingActionStart()
    {
    }

    protected function blockingActionEnd()
    {
    }

    /**
     * Performs a blocking action (request<->response pattern)
     *
     * This action sends the given command, and executes the handler upon receiving the defined event-name
     * All other sent commands are queued until the handler resolves the initial action-promise
     *
     * @param  CommandInterface $command
     * @param  string           $eventName
     * @param  callable         $handler
     * @return Promise
     */
    protected function blockingAction(CommandInterface $command, $eventName, callable $handler)
    {
        // create the deferred action to execute $handler on $eventName after sending $command
        $deferred = new Deferred();
        $actionPromise = $deferred->promise();
        $this->blockingActionStart();

        // send command
        $this->send($command, $actionPromise)->then(
            // as soon as the command is sent, register a one-time event-handler
            // on the expected response event, which executes the handler
            function (CommandInterface $sentCommand) use ($deferred, $eventName, $handler) {
                $this->connection->once($eventName, function (CommandInterface $recvCommand) use ($sentCommand, $deferred, $handler) {

                    // if the result is not NULL resolve the deferred action with the handler's result
                    // if the result is NULL we assume the handler communicated the result on the passed in deferred
                    // itself
                    $result = $handler($sentCommand, $recvCommand, $deferred);
                    if ($result !== null) {
                        $this->blockingActionEnd();
                        $deferred->resolve($result);
                    }
                });
            }
        );

        return $actionPromise;
    }

    /**
     * Sends a command and returns a promise which is resolved as soon the command is sent
     *
     * A lock-promise can be passed in optionally. All subsequent calls to send() are then queued until the
     * promise resolves
     *
     * @param  CommandInterface $command
     * @param  Promise          $lock
     * @return Promise
     */
    protected function send(CommandInterface $command, Promise $lock = null)
    {
        // the deferred action to send the data
        $deferred = new Deferred();

        // request deferred sending
        $this->sendDeferred($command, $deferred, $lock);

        // return the promise to send
        return $deferred->promise();
    }

    /**
     * INTERNAL ONLY: Sends the command and resolves the the passed in deferred as soon
     * the command is really sent.
     * Other commands are queued until an optional promise resolves (unlocks)
     *
     * @param CommandInterface $command
     * @param Deferred         $deferred
     * @param Promise          $lock
     */
    private function sendDeferred(CommandInterface $command, Deferred $deferred, Promise $lock = null)
    {
        if ($this->sendLocked === true) {
            $this->sendQueue[] = [$command, $deferred, $lock];
            return;
        }

        // if this operation is blocking:
        //  - install the given promise as lock
        //  - install a resolve-handler which removes the lock
        //  - install an error-handler to communicate the failure
        if ($lock) {
            $this->sendLocked = true;
            $lock->then(
                function () {
                    $this->sendLocked = false;
                    if (!empty($this->sendQueue)) {
                        list($command, $deferred, $lock) = array_shift($this->sendQueue);
                        $this->sendDeferred($command, $deferred, $lock);
                    }
                },
                function () {
                    throw new ProtocolException("Blocking operation failed. Protocol is in invalid state");
                }
            );
        }

        // write the command
        $this->connection->send($command)->then(function () use ($deferred, $command) {
            // resolve the the promise to send the data
            $deferred->resolve($command);
        });
    }

    /**
     * Returns the connection
     *
     * @return Connection
     */
    protected function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns the command-factory
     *
     * @return CommandFactoryInterface
     */
    protected function getCommandFactory()
    {
        return $this->connection->getCommandFactory();
    }

    public function disconnect()
    {
        $this->connection->close();
    }
}
