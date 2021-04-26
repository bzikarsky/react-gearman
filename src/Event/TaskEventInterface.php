<?php

namespace Zikarsky\React\Gearman\Event;

use Zikarsky\React\Gearman\TaskInterface;

interface TaskEventInterface
{
    public function getTask(): TaskInterface;
}
