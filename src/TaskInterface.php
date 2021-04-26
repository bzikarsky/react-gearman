<?php

namespace Zikarsky\React\Gearman;

use Evenement\EventEmitterInterface;

/**
 * A representation of a Task on the client-side
 *
 * A task represents the work-package which is submitted to the
 * job-server. It is updated with information from the worker
 * with events.
 *
 * @event complete      TaskDataEvent $event, ClientInterface $client
 * @event status        TaskStatusEvent $event, ClientInterface $client
 * @event warning       TaskDataEvent $event, ClientInterface $client
 * @event failure       TaskEvent $event, ClientInterface $client
 * @event exception     TaskDataEvent $event, ClientInterface $client
 * @event data          TaskDataEvent $event ClientInterface $client
 */
interface TaskInterface extends EventEmitterInterface
{
    public const PRIORITY_LOW      = "low";
    public const PRIORITY_NORMAL   = "";
    public const PRIORITY_HIGH     = "high";

    /**
     * Returns the function-name of the task
     */
    public function getFunction(): string;

    /**
     * Returns the job-handle of the task
     */
    public function getHandle(): string;

    /**
     * Returns the workload of this task
     */
    public function getWorkload(): ?string;

    /**
     * Returns the task's priority
     */
    public function getPriority(): string;

    /**
     * Returns the task's unique id
     */
    public function getUniqueId(): string;
}
