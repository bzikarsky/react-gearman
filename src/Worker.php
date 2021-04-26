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

    /**
     * @var bool
     */
    protected $isShutDown = false;

    /**
     * @var JobInterface[]
     */
    protected $runningJobs = [];

    /**
     * @var bool
     */
    protected $grabUniques = false;

    public function __construct(Connection $connection, bool $grabUniques = false)
    {
        parent::__construct($connection);

        $this->grabUniques = $grabUniques;

        // set up wake-up by NOOP
        $this->getConnection()->on('NOOP', [$this, 'grabJob']);

        // set up handlers for NO_JOB and JOB_ASSIGN - both are
        // responses to a grabJob
        $this->getConnection()->on('NO_JOB', [$this, 'handleNoJob']);
        $this->getConnection()->on('JOB_ASSIGN', [$this, 'handleJob']);
        $this->getConnection()->on('JOB_ASSIGN_UNIQ', [$this, 'handleUniqueJob']);

        $this->on('close', function () {
            $this->finishShutdown();
        });
    }

    /**
     * @return JobInterface[]
     */
    public function getRunningJobs()
    {
        return $this->runningJobs;
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

    public function forceShutdown()
    {
        $this->runningJobs = [];
        $this->initShutdown();
        return $this->shutdownPromise->promise();
    }

    public function shutdown()
    {
        if ($this->shutdownPromise === null) {
            $this->shutdownPromise = $shutdown = new Deferred();
            if (count($this->runningJobs) == 0) {
                $this->initShutdown();
            } else {
                $this->pause();
            }
        }

        return $this->shutdownPromise->promise();
    }

    protected function finishShutdown()
    {
        // Avoid race condition between direct shutdown + close listener, avoid double shutdown
        $this->runningJobs = [];
        if (!$this->isShutDown) {
            $this->isShutDown = true;
            if ($this->shutdownPromise !== null) {
                try {
                    $this->shutdownPromise->resolve();
                } catch (\Throwable $e) {
                    $this->shutdownPromise->reject($e);
                }
            }
        }
    }

    protected function initShutdown()
    {
        $this->disconnect();
        $this->finishShutdown();
    }

    /**
     * Stop accepting new jobs
     */
    public function pause()
    {
        $this->acceptNewJobs = false;
        if ($this->grabsInFlight <= 0) {
            $this->getConnection()->pause();
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
        $this->getConnection()->resume();
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
        $type = $this->grabUniques ? 'GRAB_JOB_UNIQ' : 'GRAB_JOB';

        $grab = $this->getCommandFactory()->create($type);
        $this->send($grab);
        $this->grabsInFlight++;
    }

    protected function handleGrabJobResponse()
    {
        $this->grabsInFlight--;
        if ($this->grabsInFlight <= 0 && !$this->acceptNewJobs) {
            $this->getConnection()->pause();
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
    }

    protected function onJobDone($handle)
    {
        unset($this->runningJobs[$handle]);
    }

    protected function makeJobSender()
    {
        return function ($command, array $payload) {
            $promise = $this->send($this->getCommandFactory()->create($command, $payload));
            // Intercept commands that finish a job
            if (in_array($command, ['WORK_COMPLETE', 'WORK_FAIL', 'WORK_EXCEPTION'])) {
                $handle = $payload['job_handle'];
                // Grab new job as soon as job is marked as completed
                $this->onInflightDone();
                $this->grabJob();

                // Mark job as done only if status has been sent successfully
                $promise->always(fn () => $this->onJobDone($handle));
            }
            return $promise;
        };
    }

    protected function handleJob(CommandInterface $command)
    {
        $this->processJob(Job::fromCommand($command, $this->makeJobSender()));
    }

    protected function handleUniqueJob(CommandInterface $command)
    {
        $this->processJob(Job::uniqueFromCommand($command, $this->makeJobSender()));
    }

    protected function processJob(JobInterface $job)
    {
        if (!isset($this->functions[$job->getFunction()])) {
            throw new \LogicException("Got job for unknown function {$job->getFunction()}");
        }

        $this->handleGrabJobResponse();
        $this->grabJob();

        // announce new job and call callback if registered
        $this->emit('new-job', [$job, $this]);
        $this->runningJobs[$job->getHandle()] = $job;
        $callback = $this->functions[$job->getFunction()];
        if (is_callable($callback)) {
            $callback($job);
        }
    }
}
