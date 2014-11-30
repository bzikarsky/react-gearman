<?php

namespace Zikarsky\React\Gearman\Event;

use Zikarsky\React\Gearman\TaskInterface;

class TaskDataEvent extends TaskEvent
{

    protected $data = "";

    public function __construct(TaskInterface $task, $data)
    {
        parent::__construct($task);

        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

}
