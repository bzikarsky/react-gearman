<?php

namespace Zikarsky\React\Gearman;

use React\Promise\Promise;
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
     *
     * @param  string $id
     * @return Promise
     */
    public function setId($id);

    /**
     * Pings the server, promise is resolved on pong.
     * Also the ping event is fired
     *
     * @return Promise
     */
    public function ping();

    /**
     * Register a function on the job-server
     *
     * The passed $callback is executed when a task for this function is
     * received. $callback is called with JobInterface $job and
     * WorkerInterface $worker.
     *
     * An optional $timeout in seconds can be defined after which the server
     * assumes the job to be timed out.
     *
     * @param  string $function
     * @param  callable $callback
     * @param  int $timeout timeout in seconds, optional, defaults to null
     * @return Promise
     */
    public function register($function, callable $callback, $timeout = null);

    /**
     * Unregister a function from the job-server
     *
     * If the functions has not been registered a RuntimeException is thrown
     *
     * @param  string $function
     * @return Promise
     * @throws RuntimeException on unknown function-name
     */
    public function unregister($function);

    /**
     * Unregisters all functions from the job-server
     *
     * @return Promise
     */
    public function unregisterAll();

    /**
     * Returns a list of all registered functions
     *
     * @return string[]
     */
    public function getRegisteredFunctions();

    /**
     * Disconnects the worker from the server
     */
    public function disconnect();

    /**
     * @param $maxParallelRequests
     * @return mixed
     */
    public function setMaxParallelRequests($maxParallelRequests);
}
