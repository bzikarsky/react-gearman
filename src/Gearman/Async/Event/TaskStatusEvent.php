<?php

namespace Gearman\Async\Event;

use Gearman\Async\TaskInterface;

class TaskStatusEvent extends TaskEvent
{
    /**
     * @var boolean
     */
    protected $known;

    /**
     * @var boolean
     */
    protected $running;

    /**
     * @var integer
     */
    protected $numerator;

    /**
     * @var integer
     */
    protected $denominator;

    /**
     * @param TaskInterface $task
     * @param boolean $known
     * @param boolean $running
     * @param integer $numerator
     * @param integer $denominator
     */
    public function __construct(TaskInterface $task, $known, $running, $numerator, $denominator)
    {
        parent::__construct($task);

        $this->known        = (bool) $known;
        $this->running      = (bool) $running;
        $this->numerator    = (int) $numerator;
        $this->denominator  = (int) $denominator;
    }

    /**
     * @return int
     */
    public function getCompletionDenominator()
    {
        return $this->denominator;
    }

    /**
     * @return boolean
     */
    public function getKnown()
    {
        return $this->known;
    }

    /**
     * @return int
     */
    public function getCompletionNumerator()
    {
        return $this->numerator;
    }

    /**
     * @return boolean
     */
    public function getRunning()
    {
        return $this->running;
    }

    /**
     * @return float
     */
    public function getCompletionPercentage()
    {
        return $this->numerator / $this->denominator;
    }
}
