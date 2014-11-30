<?php

namespace Zikarsky\React\Gearman;

use Zikarsky\React\Gearman\Protocol\Connection;
use Zikarsky\React\Gearman\Command\Binary\CommandFactoryInterface;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as EventLoopFactory;
use Zikarsky\React\Gearman\Command\Binary\DefaultCommandFactory;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\Promise\Deferred;
use React\SocketClient\Connector;

class Factory
{

    const DEFAULT_NAMESERVER = "8.8.8.8";

    protected $commandFactory = null;

    /**
     * @var LoopInterface
     */
    protected $eventLoop = null;
    protected $dnsResolver = null;
    protected $connector = null;

    public function __construct(
        LoopInterface $eventLoop = null,
        Resolver $resolver = null,
        CommandFactoryInterface $commandFactory = null
    ) {
        $this->eventLoop = $eventLoop ?: EventLoopFactory::create();
        $this->commandFactory = $commandFactory ?: new DefaultCommandFactory();

        if (!$resolver) {
            $dnsResolverFactory = new ResolverFactory();
            $resolver = $dnsResolverFactory->createCached(
                self::DEFAULT_NAMESERVER,
                $this->eventLoop
            );
        }

        $this->dnsResolver = $resolver;
        $this->connector = new Connector($this->eventLoop, $this->dnsResolver);
    }

    public function createClient($host, $port)
    {
        $deferred = new Deferred();
        $this->connector->create($host, $port)->then(
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

    public function createWorker($host, $port)
    {
        $deferred = new Deferred();
        $this->connector->create($host, $port)->then(
            function ($stream) use ($deferred) {
                $connection = new Connection($stream, $this->commandFactory);
                $client = new Worker($connection);

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

    /**
     * @return LoopInterface
     */
    public function getEventLoop()
    {
        return $this->eventLoop;
    }

}
