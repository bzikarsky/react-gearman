<?php

namespace Zikarsky\React\Gearman;

use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Evenement\EventEmitter;

class Job extends EventEmitter implements JobInterface
{
    protected $send;
    protected $function;
    protected $handle;
    protected $workload;
    protected $id;
    protected $status = self::STATUS_RUNNING;

    public function __construct(callable $sender, $function, $handle, $workload, $id = null)
    {
        $this->send = $sender;
        $this->function = $function;
        $this->handle = $handle;
        $this->workload = $workload;
        $this->id = $id;
    }

    public static function fromCommand(CommandInterface $command, callable $sender)
    {
        if (!$command->is('JOB_ASSIGN')) {
            throw new \RuntimeException('Can only create a Job from a JOB_ASSIGN command');
        }

        return new self(
            $sender,
            $command->get('function_name'),
            $command->get('job_handle'),
            $command->get(CommandInterface::DATA),
            null
        );
    }

    public static function uniqueFromCommand(CommandInterface $command, callable $sender)
    {
        if (!$command->is('JOB_ASSIGN_UNIQ')) {
            throw new \RuntimeException('Can only create a unique Job from a JOB_ASSIGN_UNIQ command');
        }

        return new self(
            $sender,
            $command->get('function_name'),
            $command->get('job_handle'),
            $command->get(CommandInterface::DATA),
            $command->get('id')
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

    public function getId()
    {
        return $this->id;
    }

    public function sendStatus($numerator, $denominator)
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $payload = [
            'job_handle'            => $this->getHandle(),
            'complete_numerator'    => $numerator,
            'complete_denominator'  => $denominator
        ];

        return call_user_func($this->send, 'WORK_STATUS', $payload);
    }

    public function sendData($data)
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $payload = [
            'job_handle'            => $this->getHandle(),
            CommandInterface::DATA  => $data
        ];

        return call_user_func($this->send, 'WORK_DATA', $payload);
    }

    public function sendWarning($warning = null)
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $payload = [
            'job_handle'            => $this->getHandle(),
            CommandInterface::DATA  => $warning
        ];

        return call_user_func($this->send, 'WORK_WARNING', $payload);
    }

    public function fail($error = null)
    {
        return null === $error
            ? $this->sendError()
            : $this->sendException($error);
    }

    protected function sendException($exception)
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $this->setStatus(self::STATUS_FAILED);

        $payload = [
            'job_handle'            => $this->getHandle(),
            CommandInterface::DATA  => $exception
        ];

        return call_user_func($this->send, 'WORK_EXCEPTION', $payload);
    }

    protected function sendError()
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $this->setStatus(self::STATUS_FAILED);

        $payload = ['job_handle' => $this->getHandle()];

        return call_user_func($this->send, 'WORK_FAIL', $payload);
    }

    public function complete($data = null)
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $this->setStatus(self::STATUS_COMPLETED);

        $payload = [
            'job_handle'            => $this->getHandle(),
            CommandInterface::DATA  => $data
        ];
        return call_user_func($this->send, 'WORK_COMPLETE', $payload);
    }

    public function getStatus()
    {
        return $this->status;
    }

    protected function setStatus($status)
    {
        $this->status = $status;
        $this->emit('status-change', [$status, $this]);
    }

    protected function assertStatus($status)
    {
        if ($status !== $this->status) {
            throw new \RuntimeException("Job is not in status {$this->status} instead of {$status}");
        }
    }
}
