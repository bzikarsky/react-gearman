<?php

use Zikarsky\React\Gearman\ClientInterface;
use Zikarsky\React\Gearman\Event\TaskDataEvent;
use Zikarsky\React\Gearman\TaskInterface;
use Zikarsky\React\Gearman\Factory;

require_once __DIR__ . "/../../vendor/autoload.php";

// use default options
$factory = new Factory();

$factory->createClient("127.0.0.1", 4730)->then(
    // on successful creation
    function (ClientInterface $client) {
        $hosts = ['google.com', 'facebook.com', 'github.com', 'wikipedia.org'];
        $client->submit("ping", serialize($hosts))->then(function (TaskInterface $task) {
            printf(
                "Pinging: %s [handle:%s]\n",
                implode(", ", unserialize($task->getWorkload())),
                $task->getHandle()
            );
            
            $task->on('data', function (TaskDataEvent $event) {
                echo "Partial update:\n";
                print_r(unserialize($event->getData()));
                echo "\n";
            });
            
            $task->on('complete', function (TaskDataEvent $event, ClientInterface $client) {
                echo "Final result: \n";
                print_r(unserialize($event->getData()));
                echo "\n";
                $client->disconnect();
            });
        });
    },
    // error-handler
    function ($error) {
        echo "Error: $error\n";
    }
);


$factory->getEventLoop()->run();
