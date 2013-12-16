<?php

namespace Gearman\Async\Event;

use Gearman\Async\TaskInterface;

interface TaskEventInterface
{

    /**
     * @return TaskInterface
     */
    public function getTask();
}
