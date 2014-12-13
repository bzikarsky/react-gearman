<?php

namespace Zikarsky\React\Gearman;

use Zikarsky\React\Gearman\Command\Binary\CommandInterface;

class Job implements JobInterface
{

    protected $worker;
    protected $function;
    protected $handle;
    protected $workload;

    public function __construct(WorkerInterface $worker, $function, $handle, $workload)
    {
        $this->worker = $worker;
        $this->function = $function;
        $this->handle = $handle;
        $this->workload = $workload;
    }

    public static function fromCommand(CommandInterface $command, WorkerInterface $worker)
    {
        if ($command->getName() != 'JOB_ASSIGN') {
            throw new \RuntimeException('Can only create a Job from a JOB_ASSIGN command');
        }

        return new self(
            $worker, 
            $command->get('function_name'), 
            $command->get('job_handle'), 
            $command->get(CommandInterface::DATA)
        );
    }

    public function getFunction()
    {
        return $this->function;
    }

    public function getHandle()
    {
        return $this->handle;
    }

    public function getWorkload()
    {
        return $this->workload;
    }

    public function sendStatus($numerator, $denominator)
    {
        return $this->worker->sendJobStatus($this, $numerator, $denominator);
    }

    public function sendData($data)
    {
        return $this->worker->sendJobData($this, $data);
    }

    public function sendWarning($warning)
    {
        return $this->worker->sendJobWarning($this, $warning);
    }
}
