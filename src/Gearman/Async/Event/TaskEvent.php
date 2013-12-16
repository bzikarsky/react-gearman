<?php

namespace Gearman\Async\Event;

use Gearman\Async\TaskInterface;

class TaskEvent implements TaskEventInterface
{

    /**
     * @var TaskInterface
     */
    protected $task;

    /**
     * Creates a task event
     *
     * @param TaskInterface $task
     */
    public function __construct(TaskInterface $task)
    {
        $this->task = $task;
    }

    /**
     * @return TaskInterface
     */
    public function getTask()
    {
        return $this->task;
    }

}
