<?php

namespace Zikarsky\React\Gearman;

use Psr\Log\AbstractLogger;
use Psr\Log\Test\TestLogger;
use React\Socket\DnsConnector;
use React\Socket\TcpConnector;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Zikarsky\React\Gearman\Protocol\Connection;
use Zikarsky\React\Gearman\Command\Binary\CommandFactoryInterface;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as EventLoopFactory;
use Zikarsky\React\Gearman\Command\Binary\DefaultCommandFactory;
use React\Promise\Deferred;

class Factory
{
    protected $commandFactory = null;

    /**
     * @var LoopInterface
     */
    protected $eventLoop = null;

    /**
     * @var ConnectorInterface
     */
    protected $connector = null;

    /**
     * Factory for gearman server's connection.
     * Passing a dns resolver is optional. Using it without a resolver may cause an exception
     * caused by React\SocketClient\TcpConnector.
     *
     * @param LoopInterface|null $eventLoop
     * @param Resolver|null $resolver an optional dns resolver
     * @param CommandFactoryInterface|null $commandFactory
     */
    public function __construct(
        LoopInterface $eventLoop = null,
        Resolver $resolver = null,
        CommandFactoryInterface $commandFactory = null
    ) {
        $this->eventLoop = $eventLoop ?: EventLoopFactory::create();
        $this->commandFactory = $commandFactory ?: new DefaultCommandFactory();

        if ($resolver !== null) {
            $this->connector = new DnsConnector(new TcpConnector($this->eventLoop), $resolver);
        } else {
            $this->connector = new TcpConnector($this->eventLoop);
        }
    }

    public function createClient($host, $port)
    {
        $deferred = new Deferred();
        $this->connector->connect($host . ':' . $port)->then(
            function ($stream) use ($deferred) {
                $connection = new Connection($stream, $this->commandFactory);
//                $connection->setLogger(new class extends AbstractLogger {
//                    public function log($level, $message, array $context = array())
//                    {
//                        echo "[CLIENT][$level] $message}\n";
//                    }
//                });
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

    public function createWorker($host, $port, bool $grabUniques = false)
    {
        $deferred = new Deferred();
        $this->connector->connect($host . ':' . $port)->then(
            function ($stream) use ($deferred, $grabUniques) {
                $connection = new Connection($stream, $this->commandFactory);
//                $connection->setLogger(new class extends AbstractLogger {
//                    public function log($level, $message, array $context = array())
//                    {
//                        echo "[WORKER][$level] $message}\n";
//                    }
//                });
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

    /**
     * @return LoopInterface
     */
    public function getEventLoop()
    {
        return $this->eventLoop;
    }
}
