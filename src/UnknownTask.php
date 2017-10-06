<?php

namespace Zikarsky\React\Gearman;

use Evenement\EventEmitter;

class UnknownTask extends EventEmitter implements TaskInterface
{

    /**
     * @var string
     */
    protected $handle;

    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Returns null since the submitted function of an unknown task is unknown
     *
     * @return null
     */
    public function getFunction()
    {
        return null;
    }

    /**
     * Returns null since the submitted workload of an unknown task is unknown
     *
     * @return null
     */
    public function getWorkload()
    {
        return null;
    }

    /**
     * Returns null since the priority of an unknown task is unknown
     *
     * @return null
     */
    public function getPriority()
    {
        return null;
    }

    /**
     * Returns null since the id of an unknown task is unknown
     *
     * @return null
     */
    public function getUniqueId()
    {
        return null;
    }

}
