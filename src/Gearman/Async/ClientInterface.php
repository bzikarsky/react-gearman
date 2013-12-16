<?php

namespace Gearman\Async;

use React\Promise\Promise;

/**
 * An async Gearman client
 *
 * @event task-submitted    TaskInterface $task, ClientInterface $client
 * @event ping              ClientInterface $client
 * @event option            string $option, ClientInterface $client
 * @event status            TaskStatusEvent $status
 */
interface ClientInterface
{
    const OPTION_FORWARD_EXCEPTIONS = "exceptions";

    /**
     * Pings the server, promise is resolved on pong
     * Also the ping event is fired
     *
     * @return Promise
     */
    public function ping();

    /**
     * Submits a task to the server, promise is resolved on task creation with a TaskInterface
     * Also the task-created event is fired
     *
     * @param string    $function
     * @param string    $workload
     * @param string    $priority   defaults to TaskInterface::PRIORITY_NORMAL
     * @return Promise
     */
    public function submit($function, $workload, $priority);

    /**
     * Sets an option for the client, promise is resolved when option is set with the option_name
     * Also the option event is fired
     *
     * @param string $option
     * @return Promise
     */
    public function setOption($option);

    /**
     * Requests the status for given job-handle or task, the promise is resolved with the TaskStatusEvent
     * Also the status event will be fired on the Client
     * If the task is known (was submitted on this client instance) the status event on the task will be emitted
     *
     * @param string|TaskInterface  $task
     * @return Promise
     */
    public function getStatus($task);
}
