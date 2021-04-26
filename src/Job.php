<?php

namespace Zikarsky\React\Gearman;

use Closure;
use React\Promise\PromiseInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Evenement\EventEmitter;

class Job extends EventEmitter implements JobInterface
{
    protected Closure $send;
    protected string $function;
    protected string $handle;
    protected ?string $workload;
    protected ?string $id;
    protected string $status = self::STATUS_RUNNING;

    public function __construct(callable $sender, string $function, string $handle, ?string $workload, ?string $id = null)
    {
        $this->send = Closure::fromCallable($sender);
        $this->function = $function;
        $this->handle = $handle;
        $this->workload = $workload;
        $this->id = $id;
    }

    public static function fromCommand(CommandInterface $command, callable $sender): self
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

    public static function uniqueFromCommand(CommandInterface $command, callable $sender): self
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

    public function getFunction(): string
    {
        return $this->function;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getWorkload(): ?string
    {
        return $this->workload;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function sendStatus(int $numerator, int $denominator): PromiseInterface
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $payload = [
            'job_handle'            => $this->getHandle(),
            'complete_numerator'    => $numerator,
            'complete_denominator'  => $denominator
        ];

        return call_user_func($this->send, 'WORK_STATUS', $payload);
    }

    public function sendData(string $data): PromiseInterface
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $payload = [
            'job_handle'            => $this->getHandle(),
            CommandInterface::DATA  => $data
        ];

        return call_user_func($this->send, 'WORK_DATA', $payload);
    }

    public function sendWarning(?string $warning = null): PromiseInterface
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $payload = [
            'job_handle'            => $this->getHandle(),
            CommandInterface::DATA  => $warning
        ];

        return call_user_func($this->send, 'WORK_WARNING', $payload);
    }

    public function fail(?String $error = null): PromiseInterface
    {
        return null === $error
            ? $this->sendError()
            : $this->sendException($error);
    }

    protected function sendException(string $exception): PromiseInterface
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $this->setStatus(self::STATUS_FAILED);

        $payload = [
            'job_handle'            => $this->getHandle(),
            CommandInterface::DATA  => $exception
        ];

        return call_user_func($this->send, 'WORK_EXCEPTION', $payload);
    }

    protected function sendError(): PromiseInterface
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $this->setStatus(self::STATUS_FAILED);

        $payload = ['job_handle' => $this->getHandle()];

        return call_user_func($this->send, 'WORK_FAIL', $payload);
    }

    public function complete(?string $data = null): PromiseInterface
    {
        $this->assertStatus(self::STATUS_RUNNING);
        $this->setStatus(self::STATUS_COMPLETED);

        $payload = [
            'job_handle'            => $this->getHandle(),
            CommandInterface::DATA  => $data
        ];
        return call_user_func($this->send, 'WORK_COMPLETE', $payload);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    protected function setStatus(string $status): void
    {
        $this->status = $status;
        $this->emit('status-change', [$status, $this]);
    }

    protected function assertStatus(string $status): void
    {
        if ($status !== $this->status) {
            throw new \RuntimeException("Job is not in status {$this->status} instead of {$status}");
        }
    }
}
