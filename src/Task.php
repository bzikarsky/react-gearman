<?php

namespace Zikarsky\React\Gearman;

use Evenement\EventEmitter;

class Task extends EventEmitter implements TaskInterface
{
    /**
     * @var string
     */
    protected $function;

    /**
     * @var string
     */
    protected $workload;

    /**
     * @var string
     */
    protected $handle;

    /**
     * @var string
     */
    protected $priority;

    /**
     * @var string
     */
    protected $uniqueId;

    public function __construct($function, $workload, $handle, $priority, $uniqueId)
    {
        $this->function = $function;
        $this->workload = $workload;
        $this->handle   = $handle;
        $this->priority = $priority;
        $this->uniqueId = $uniqueId;
    }

    /**
     * @return string
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getWorkload()
    {
        return $this->workload;
    }

    /**
     * @return string
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @return string
     */
    public function getUniqueId()
    {
        return $this->uniqueId;
    }
}
