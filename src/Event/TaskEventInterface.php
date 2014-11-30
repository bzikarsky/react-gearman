<?php

namespace Zikarsky\React\Gearman\Event;

use Zikarsky\React\Gearman\TaskInterface;

interface TaskEventInterface
{

    /**
     * @return TaskInterface
     */
    public function getTask();
}
