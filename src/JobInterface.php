<?php

namespace Zikarsky\React\Gearman;

use React\Promise\Promise;
use React\Promise\PromiseInterface;

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
    public const STATUS_RUNNING = "running";
    public const STATUS_COMPLETED = "completed";
    public const STATUS_FAILED = "failed";


    /**
      * Get the function-name of this job
      */
    public function getFunction(): string;

    /**
     * Returns the job-handle of this job
     */
    public function getHandle(): string;

    /**
     * Returns the workload of this job
     */
    public function getWorkload(): ?string;

    /**
     * Sends the current status of this job to the server
     */
    public function sendStatus(int $numerator, int $denominator): PromiseInterface;

    /**
     * Sends data for the job to the job-server
     *
     * @param  string data
     * @return Promise
     */
    public function sendData(string $data): PromiseInterface;

    /**
     * Sends a warning for this job to the job-server
     */
    public function sendWarning(?string $warning = null): PromiseInterface;

    /**
     * Sends the result data and marks job as completed
     */
    public function complete(?string $data = null): PromiseInterface;

    /**
     * Fails the job with optional error message
     */
    public function fail(?string $error = null): PromiseInterface;

    public function getId(): ?string;
}
