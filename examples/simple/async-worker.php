<?php

use Zikarsky\React\Gearman\WorkerInterface;
use Zikarsky\React\Gearman\Event\TaskDataEvent;
use Zikarsky\React\Gearman\JobInterface;
use Zikarsky\React\Gearman\Factory;

require_once __DIR__ . "/../vendor/autoload.php";

// use default options
$factory = new Factory();

$factory->createWorker("127.0.0.1", 4730)->then(
    // on successful creation
    function (WorkerInterface $worker) {
        $worker->setId('Test-Client/' . getmypid());
        $worker->register('reverse', function(JobInterface $job) {
            echo "Job: ", $job->getHandle(), ": ", $job->getFunction(), 
                 " with ", $job->getWorkload(), "\n";

            $job->complete(strrev($job->getWorkload()));
        });
    },
    // error-handler
    function($error) {
        echo "Error: $error\n";
    }
);

$factory->getEventLoop()->run();
