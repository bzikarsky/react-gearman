<?php

namespace Zikarsky\React\Gearman;

use Zikarsky\React\Gearman\Protocol\Connection;

class Worker extends Participant implements WorkerInterface
{

    protected $functions = [];

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

    public function register($function, callable $callback, $timeout = null)
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
        $promise->then(function() use ($function, $callback) {
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

        $promise->then(function() use ($function) {
            unset($this->functions[$function]);
            $this->grabJob();
        });

        return $promise;
    }

    public function unregisterAll()
    {
        $command = $this->getCommandFactory()->create('RESET_ABILITIES');
        $promise = $this->send($command);

        $promise->then(function() {
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

    protected function grabJob()
    {
        $grab = $this->getCommandFactory()->create('GRAB_JOB');
        $this->send($grab);
    }

    protected function handleNoJob()
    {
        $preSleep = $this->getCommandFactory()->create('PRE_SLEEP');
        $this->send($preSleep);
    }

    protected function handleJob(CommandInterface $command)
    {
        $job = Job::FromCommand($command, $this);

        if (!isset($this->functions[$job->getFunction()])) {
            throw new \LogicException("Got job for unknown function {$job->getFunction}");
        }

        $callback = $this->function[$job->getFunction()];
        $this->emit('new-job', [$job, $this]);

        try {
            $result = $callback($job, $this);
            return $this->sendJobComplete($job, $result);
        } catch (\Exception $e) {
            return $this->sendJobException($job, $e);
        }

        $this->grabJob();
    }

    protected function sendJobComplete(JobInterface $job, $result)
    {
    }

    protected function sendJobException(JobInterface $job, \Exception $e)
    {
    }
}
