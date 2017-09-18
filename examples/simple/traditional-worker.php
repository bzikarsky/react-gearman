<?php

// Taken from http://www.php.net/manual/en/gearmanworker.wait.php example#1

echo "Starting\n";

# Create our worker object
$worker= new GearmanWorker();

# Make the worker non-blocking
$worker->addOptions(GEARMAN_WORKER_NON_BLOCKING);

# Add the default server (localhost, port 4730)
$worker->addServer("127.0.0.1", 4730);

# Add our reverse function
$worker->addFunction('reverse', 'reverse_fn');

# Try to grab a job
while (@$worker->work() ||
       $worker->returnCode() == GEARMAN_IO_WAIT ||
       $worker->returnCode() == GEARMAN_NO_JOBS) {
    if ($worker->returnCode() == GEARMAN_SUCCESS) {
        continue;
    }

    echo "Waiting for next job...\n";
    if (!@$worker->wait()) {
        if ($worker->returnCode() == GEARMAN_NO_ACTIVE_FDS) {
            # We are not connected to any servers, so wait a bit before
            # trying to reconnect.
            sleep(5);
            continue;
        }
        break;
    }
}

echo "Worker Error: " . $worker->error() . "\n";

function reverse_fn($job)
{
    return strrev($job->workload());
}
