<?php

namespace Zikarsky\React\Gearman;

use React\Promise\Promise;
use React\Promise\PromiseInterface;

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
    public const OPTION_FORWARD_EXCEPTIONS = "exceptions";

    /**
     * Pings the server, promise is resolved on pong
     * Also the ping event is fired
     */
    public function ping(): PromiseInterface;

    /**
     * Submits a task to the server, promise is resolved on task creation with a TaskInterface
     * Also the task-created event is fired
     */
    public function submit(string $function, ?string $workload = null, string $priority = TaskInterface::PRIORITY_NORMAL, string $uniqueId = ''): PromiseInterface;

    /**
     * Submits a task to the server, promise is resolved on task creation with a TaskInterface
     * Also the task-created event is fired
     */
    public function submitBackground(string $function, ?string $workload = null, string $priority = TaskInterface::PRIORITY_NORMAL, string $uniqueId = ''): PromiseInterface;

    /**
     * Sets an option for the client, promise is resolved when option is set with the option_name
     * Also the option event is fired
     */
    public function setOption(string $option): PromiseInterface;

    /**
     * Requests the status for given job-handle or task, the promise is resolved with the TaskStatusEvent
     * Also the status event will be fired on the Client
     * If the task is known (was submitted on this client instance) the status event on the task will be emitted
     *
     * @param  string|TaskInterface $task
     */
    public function getStatus($task): PromiseInterface;

    /**
     * Cancel a task and its promise resolution
     */
    public function cancel(TaskInterface $task): void;

    /**
     * Disconnects the client from the server
     */
    public function disconnect(): void;

    /**
     * Waits until all pending tasks + submits have finished
     */
    public function wait(): PromiseInterface;
}
