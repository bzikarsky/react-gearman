<?php

namespace Zikarsky\React\Gearman;

use React\Promise\Deferred;
use Zikarsky\React\Gearman\Protocol\Connection;
use Zikarsky\React\Gearman\Protocol\Participant;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;

class Worker extends Participant implements WorkerInterface
{
    protected $functions = [];

    /**
     * @var int
     */
    protected $maxParallelRequests = 1;

    /**
     * @var int
     */
    protected $inflightRequests = 0;

    /**
     * @var bool
     */
    protected $acceptNewJobs = true;

    /**
     * @var int
     */
    protected $grabsInFlight = 0;

    /**
     * @var Deferred
     */
    protected $shutdownPromise = null;

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);

        // set up wake-up by NOOP
        $this->getConnection()->on('NOOP', [$this, 'grabJob']);

        // set up handlers for NO_JOB and JOB_ASSIGN - both are
        // responses to a grabJob
        $this->getConnection()->on('NO_JOB', [$this, 'handleNoJob']);
        $this->getConnection()->on('JOB_ASSIGN', [$this, 'handleJob']);
    }

    /**
     * @return int
     */
    public function getInflightRequests()
    {
        return $this->inflightRequests;
    }

    /**
     * To make full use of async I/O, jobs can be accepted and processed in parallel
     * {$$maxParallelRequests} limits the number of jobs that are accepted from
     * the gearman server before an active job has to complete or fail
     *
     * @param int $maxParallelRequests
     */
    public function setMaxParallelRequests($maxParallelRequests)
    {
        $this->maxParallelRequests = $maxParallelRequests;
    }

    public function setId($id)
    {
        $command = $this->getCommandFactory()->create('SET_CLIENT_ID', [
            'worker_id' => $id
        ]);

        return $this->send($command);
    }

    public function register($function, callable $callback = null, $timeout = null)
    {
        $type = 'CAN_DO';
        $data = ['function_name' => $function];

        if ($timeout !== null) {
            $type .= '_TIMEOUT';
            $data['timeout'] = intval($timeout);
        }

        $command = $this->getCommandFactory()->create($type, $data);
        $promise = $this->send($command);

        // as soon the function is registered:
        //  1) we now officially know about this function
        //  2)  let's check if there is work for us
        $promise->then(function () use ($function, $callback) {
            $this->functions[$function] = $callback;
            $this->grabJob();
        });

        return $promise;
    }

    public function unregister($function)
    {
        if (!isset($this->functions[$function])) {
            throw new \RuntimeException("Cannot unregister unknown function $function");
        }

        $command = $this->getCommandFactory->create("CANT_DO", ['function_name' => $function]);
        $promise = $this->send($command);

        $promise->then(function () use ($function) {
            unset($this->functions[$function]);
            $this->grabJob();
        });

        return $promise;
    }

    public function unregisterAll()
    {
        $command = $this->getCommandFactory()->create('RESET_ABILITIES');
        $promise = $this->send($command);

        $promise->then(function () {
            $this->functions = [];
        });

        return $promise;
    }

    public function getRegisteredFunctions()
    {
        return array_values($this->functions);
    }

    public function disconnect()
    {
        $this->unregisterAll();

        parent::disconnect();
    }

    public function shutdown()
    {
        if ($this->shutdownPromise === null) {
            $this->shutdownPromise = new Deferred();
            $this->pause();
        }

        return $this->shutdownPromise->promise();
    }

    /**
     * Stop accepting new jobs
     */
    public function pause()
    {
        $this->acceptNewJobs = false;
        if ($this->grabsInFlight <= 0) {
            $this->getConnection()->stream->pause();
        }
    }

    /**
     * Accept new jobs again
     */
    public function resume()
    {
        if ($this->shutdownPromise !== null) {
            throw new \RuntimeException("Worker is shutting down");
        }
        $this->acceptNewJobs = true;
        $this->getConnection()->stream->resume();
        $this->grabJob();
    }

    protected function grabJob()
    {
	    if ($this->acceptNewJobs && $this->inflightRequests < $this->maxParallelRequests) {
            $this->inflightRequests++;
            $this->grabJobSend();
        }
    }

    protected function grabJobSend()
    {
        $grab = $this->getCommandFactory()->create('GRAB_JOB');
        $this->send($grab);
        $this->grabsInFlight++;
    }

    protected function handleGrabJobResponse()
    {
        $this->grabsInFlight--;
        if ($this->grabsInFlight <= 0 && !$this->acceptNewJobs) {
            $this->getConnection()->stream->pause();
        }

        return $this->acceptNewJobs;
    }

    protected function handleNoJob()
    {
        $this->onInflightDone();
        $this->handleGrabJobResponse();
        if ($this->acceptNewJobs) {
            $preSleep = $this->getCommandFactory()->create('PRE_SLEEP');
            $this->send($preSleep);
        }
    }

    protected function onInflightDone()
    {
        $this->inflightRequests--;
        if ($this->inflightRequests <= 0 && $this->shutdownPromise !== null) {
            $this->disconnect();
            $this->shutdownPromise->resolve();
        }
    }

    protected function handleJob(CommandInterface $command)
    {
        $job = Job::FromCommand($command, function ($command, array $payload) {
            $promise = $this->send($this->getCommandFactory()->create($command, $payload));
            // grab next job, when this one is completed or failed
            if (in_array($command, ['WORK_COMPLETE', 'WORK_FAIL'])) {
                $promise->then(function () {
                    $this->onInflightDone();
                    $this->grabJob();
                }, function () {
                    $this->onInflightDone();
                    $this->grabJob();
                });
            }
            return $promise;
        });

        if (!isset($this->functions[$job->getFunction()])) {
            throw new \LogicException("Got job for unknown function {$job->getFunction()}");
        }

        $this->handleGrabJobResponse();
        $this->grabJob();

        // announce new job and call callback if registered
        $this->emit('new-job', [$job, $this]);
        $callback = $this->functions[$job->getFunction()];
        if (is_callable($callback)) {
            $callback($job);
        }
    }
}
