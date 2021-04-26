<?php

namespace Zikarsky\React\Gearman\Event;

use Zikarsky\React\Gearman\TaskInterface;

class TaskEvent implements TaskEventInterface
{
    protected TaskInterface $task;

    public function __construct(TaskInterface $task)
    {
        $this->task = $task;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }
}
