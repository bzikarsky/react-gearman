<?php

use Zikarsky\React\Gearman\WorkerInterface;
use Zikarsky\React\Gearman\Event\TaskDataEvent;
use Zikarsky\React\Gearman\JobInterface;
use Zikarsky\React\Gearman\Factory;
use React\Promise\Deferred;
use React\Promise;

require_once __DIR__ . "/../../vendor/autoload.php";

// use default options
$factory = new Factory();

$factory->createWorker("127.0.0.1", 4730)->then(
    // on successful creation
    function (WorkerInterface $worker) use ($factory) {
        $worker->setId('Test-Client/' . getmypid());
        $worker->register('ping', function (JobInterface $job) {
            $result = [];
            $hosts  = unserialize($job->getWorkload());

            $pingHost = function () use (&$hosts, &$result, $job, &$pingHost) {
                $host = array_shift($hosts);
                echo "ping: $host\n";
                $result[$host] = trim(`ping -c 2 -q $host | grep rtt`);

                $dataTransmitted = $job->sendData($result);
                $statusUpdated   = $job->sendStatus(count($result), count($result) + count($hosts));

                if (count($hosts) > 0) {
                    Promise\all([$dataTransmitted, $statusUpdated])->then($pingHost);
                } else {
                    $job->complete($result);
                }
            };

            $pingHost();
        });
    },
    // error-handler
    function ($error) {
        echo "Error: $error\n";
    }
);

$factory->getEventLoop()->run();
