<?php

namespace Zikarsky\React\Gearman;

use React\Promise\Promise;

/**
 * A Job is the representation of a unit-of-work on the worker-side.
 *
 * It's the interface to update the client with information about the job's
 * status.
 *
 * @event status-change $status, JobInterface fires when the job's status changes
 */
interface JobInterface
{
    const STATUS_RUNNING = "running";
    const STATUS_COMPLETED = "completed";
    const STATUS_FAILED = "failed";


    /**
      * Get the function-name of this job
      *
      * @return string
      */
    public function getFunction();

    /**
     * Returns the job-handle of this job
     *
     * @return string
     */
    public function getHandle();

    /**
     * Returns the workload of this job
     *
     * @return string
     */
    public function getWorkload();

    /**
     * Sends the current status of this job to the server
     *
     * @param  int $numerator
     * @param  int $denominator
     * @return Promise
     */
    public function sendStatus($numerator, $denominator);

    /**
     * Sends data for the job to the job-server
     *
     * @param  string data
     * @return Promise
     */
    public function sendData($data);

    /**
     * Sends a warning for this job to the job-server
     *
     * @param  string data
     * @return Promise
     */
    public function sendWarning($warning);
}
