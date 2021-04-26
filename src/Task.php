<?php

namespace Zikarsky\React\Gearman;

use Evenement\EventEmitter;

class Task extends EventEmitter implements TaskInterface
{
    protected string $function;
    protected ?string $workload;
    protected string $handle;
    protected string $priority;
    protected string $uniqueId;

    public function __construct(string $function, ?string $workload, string $handle, string $priority, string $uniqueId)
    {
        $this->function = $function;
        $this->workload = $workload;
        $this->handle   = $handle;
        $this->priority = $priority;
        $this->uniqueId = $uniqueId;
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

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }
}
