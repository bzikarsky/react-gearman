<?php

namespace Zikarsky\React\Gearman;

use React\Promise\PromiseInterface;
use React\Socket\ConnectorInterface;
use React\Socket\DnsConnector;
use React\Socket\TcpConnector;
use Zikarsky\React\Gearman\Protocol\Connection;
use Zikarsky\React\Gearman\Command\Binary\CommandFactoryInterface;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as EventLoopFactory;
use Zikarsky\React\Gearman\Command\Binary\DefaultCommandFactory;
use React\Promise\Deferred;

class Factory
{
    protected CommandFactoryInterface $commandFactory;
    protected LoopInterface $eventLoop;
    protected ?ConnectorInterface $connector;

    /**
     * Factory for gearman server's connection.
     * Passing a dns resolver is optional. Using it without a resolver may cause an exception
     * caused by React\SocketClient\TcpConnector.
     */
    public function __construct(
        ?LoopInterface $eventLoop = null,
        ?Resolver $resolver = null,
        ?CommandFactoryInterface $commandFactory = null
    ) {
        $this->eventLoop = $eventLoop ?: EventLoopFactory::create();
        $this->commandFactory = $commandFactory ?: new DefaultCommandFactory();

        if ($resolver !== null) {
            $this->connector = new DnsConnector(new TcpConnector($this->eventLoop), $resolver);
        } else {
            $this->connector = new TcpConnector($this->eventLoop);
        }
    }

    public function createClient(string $host, int $port): PromiseInterface
    {
        $deferred = new Deferred();
        $this->connector->connect($host . ':' . $port)->then(
            function ($stream) use ($deferred) {
                $connection = new Connection($stream, $this->commandFactory);
                $client = new Client($connection);

                $client->ping()->then(
                    function () use ($deferred, $client) {
                        $deferred->resolve($client);
                    },
                    function () use ($deferred) {
                        $deferred->reject("Initial test ping failed.");
                    }
                );
            },
            function ($error) use ($deferred) {
                $deferred->reject("Stream connect failed: $error");
            }
        );

        return $deferred->promise();
    }

    public function createWorker(string $host, int $port, bool $grabUniques = false): PromiseInterface
    {
        $deferred = new Deferred();
        $this->connector->connect($host . ':' . $port)->then(
            function ($stream) use ($deferred, $grabUniques) {
                $connection = new Connection($stream, $this->commandFactory);
                $client = new Worker($connection, $grabUniques);

                $client->ping()->then(
                    function () use ($deferred, $client) {
                        $deferred->resolve($client);
                    },
                    function () use ($deferred) {
                        $deferred->reject("Initial test ping failed.");
                    }
                );
            },
            function ($error) use ($deferred) {
                $deferred->reject("Stream connect failed: $error");
            }
        );

        return $deferred->promise();
    }

    public function getEventLoop(): LoopInterface
    {
        return $this->eventLoop;
    }
}
