<?php


$hosts = ['google.com', 'wikipedia.org', 'facebook.com', 'github.com'];
$client = new GearmanClient();
$client->addServer();


$client->setDataCallback(function($task) {
    echo "Partial update:\n";
    print_r(unserialize($task->data()));
});

$client->setCompleteCallback(function($task) {
    echo "Final result:\n";
    print_r(unserialize($task->data()));
});

$client->addTask('ping', serialize($hosts));


echo "Pinging: ", implode(', ', $hosts), ":\n";


$client->runTasks();

