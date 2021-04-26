<?php

namespace Zikarsky\React\Gearman\Event;

use Zikarsky\React\Gearman\TaskInterface;

class TaskDataEvent extends TaskEvent
{
    protected string $data = "";

    public function __construct(TaskInterface $task, string $data)
    {
        parent::__construct($task);

        $this->data = $data;
    }

    public function getData(): string
    {
        return $this->data;
    }
}
