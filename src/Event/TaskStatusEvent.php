<?php

namespace Zikarsky\React\Gearman\Event;

use Zikarsky\React\Gearman\TaskInterface;

class TaskStatusEvent extends TaskEvent
{
    protected bool $known;
    protected bool $running;
    protected int $numerator;
    protected int $denominator;

    public function __construct(TaskInterface $task, bool $known, bool $running, int $numerator, int $denominator)
    {
        parent::__construct($task);

        $this->known        = $known;
        $this->running      = $running;
        $this->numerator    = $numerator;
        $this->denominator  = $denominator;
    }

    public function getCompletionDenominator(): int
    {
        return $this->denominator;
    }

    public function getKnown(): bool
    {
        return $this->known;
    }

    public function getCompletionNumerator(): int
    {
        return $this->numerator;
    }

    public function getRunning(): bool
    {
        return $this->running;
    }

    public function getCompletionPercentage(): float
    {
        return $this->numerator / $this->denominator;
    }
}
