<?php

namespace Zikarsky\React\Gearman;

use Zikarsky\React\Gearman\Protocol\Connection;
use Zikarsky\React\Gearman\Protocol\Participant;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;


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

    public function setId($id)
    {
        $command = $this->getCommandFactory()->create('SET_CLIENT_ID', [
            'worker_id' => $id
        ]);

        return $this->send($command);
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

        $callback = $this->functions[$job->getFunction()];
        $this->emit('new-job', [$job, $this]);

        try {
            $result = $callback($job, $this);
            $this->sendJobComplete($job, $result);
        } catch (\Exception $e) {
            $this->sendJobException($job, $e);
        }

        $this->grabJob();
    }

    protected function sendJobComplete(JobInterface $job, $result)
    {
        $command = $this->getCommandFactory()->create('WORK_COMPLETE', [
            'job_handle'            => $job->getHandle(),
            CommandInterface::DATA  => $result
        ]);

        $this->send($command);
    }

    protected function sendJobException(JobInterface $job, \Exception $e)
    {
        $command = $this->getCommandFactory()->create('WORK_EXCEPTION', [
            'job_handle'            => $job->getHandle(),
            CommandInterface::DATA  => serialize($e)
        ]);

        $this->send($command);
    }

    public function sendJobStatus(JobInterface $job, $numerator, $denominator)
    {
        $command = $this->getCommandFactory()->create('WORK_STATUS', [
            'job_handle'            => $job->getHandle(),
            'complete_numerator'    => $numerator,
            'complete_denominator'  => $denominator
        ]);

        $this->send($command);       
    }

    public function sendJobData(JobInterface $job, $data)
    {
        $command = $this->getCommandFactory()->create('WORK_DATA', [
            'job_handle'            => $job->getHandle(),
            CommandInterface::DATA  => $data
        ]);

        $this->send($command);
    }

    public function sendJobWarning(JobInterface $job, $warning)
    {
        $command = $this->getCommandFactory()->create('WORK_WARNING', [
            'job_handle'            => $job->getHandle(),
            CommandInterface::DATA  => $warning
        ]);

        $this->send($command);
    }
}
