<?php

namespace Zikarsky\React\Gearman;

use React\Promise\Promise;
use React\Promise\PromiseInterface;
use RuntimeException;

/**
 * An async Gearman worker
 *
 * @event new-job   JobInterface $job, WorkerInterface $worker
 * @event ping      WorkerInterface $worker
 */
interface WorkerInterface
{
    /**
     * Sets the worker's ID for server monitoring/reporting
     */
    public function setId(string $id): PromiseInterface;

    /**
     * Pings the server, promise is resolved on pong.
     * Also the ping event is fired
     */
    public function ping(): PromiseInterface;

    /**
     * Register a function on the job-server
     *
     * The passed $callback is executed when a task for this function is
     * received. $callback is called with JobInterface $job and
     * WorkerInterface $worker.
     *
     * An optional $timeout in seconds can be defined after which the server
     * assumes the job to be timed out.
     */
    public function register(string $function, callable $callback, ?int $timeout = null): PromiseInterface;

    /**
     * Unregister a function from the job-server
     *
     * If the functions has not been registered a RuntimeException is thrown

     * @throws RuntimeException on unknown function-name
     */
    public function unregister(string $function): PromiseInterface;

    /**
     * Unregisters all functions from the job-server
     */
    public function unregisterAll(): PromiseInterface;

    /**
     * Returns a list of all registered functions
     *
     * @return string[]
     */
    public function getRegisteredFunctions(): array;

    /**
     * Disconnects the worker from the server
     */
    public function disconnect(): void;

    public function setMaxParallelRequests(int $maxParallelRequests): void;

    /**
     * Stop accepting new jobs
     */
    public function pause(): void;

    /**
     * Accept new jobs again
     */
    public function resume(): void;

    /**
     * @return JobInterface[]
     */
    public function getRunningJobs(): array;

    /**
     * Shutdown worker gracefully
     * Stop accepting new jobs, Process all pending jobs, then disconnect
     */
    public function shutdown(): PromiseInterface;

    /**
     * Shut down immediately. Do not wait for jobs to finish
     */
    public function forceShutdown(): PromiseInterface;
}
