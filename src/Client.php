<?php

namespace Zikarsky\React\Gearman;

use Zikarsky\React\Gearman\Event\TaskDataEvent;
use Zikarsky\React\Gearman\Event\TaskEvent;
use Zikarsky\React\Gearman\Event\TaskStatusEvent;
use Zikarsky\React\Gearman\Protocol\Connection;
use Zikarsky\React\Gearman\Protocol\Participant;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Exception as ProtocolException;
use React\Promise\Promise;

/**
 * A client can submit work requests to a Gearman server
 *
 * All the methods interacting with the server will return promises which get resolved as soon the
 * request is done. The events returned by submit, will get their own events fired, as soon the client receives
 * them
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
class Client extends Participant implements ClientInterface
{
    /**
     * @var string[]
     */
    private static $workEvents = [
        "WORK_COMPLETE",
        "WORK_STATUS",
        "WORK_FAIL",
        "WORK_EXCEPTION",
        "WORK_DATA",
        "WORK_WARNING"
    ];

    /**
     * @var TaskInterface[]
     */
    protected $tasks = [];

    /**
     * Creates the client on top of the given connection
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);

        foreach (self::$workEvents as $event) {
            $this->getConnection()->on($event, [$this, 'handleWorkEvent']);
        }
    }

    /**
     * Submits the given work-request (function, workload) at the given priority
     * The promise resolves with a representing TaskInterface instance as soon the server
     * confirms the queuing of the task
     *
     * @param  string  $function
     * @param  string  $workload
     * @param  string  $priority
     * @return Promise
     */
    public function submit($function, $workload = "", $priority = TaskInterface::PRIORITY_NORMAL)
    {
        $type = "SUBMIT_JOB" . ($priority == "" ? "" : "_" . strtoupper($priority));
        $command = $this->getCommandFactory()->create($type,[
            'function_name'             => $function,
            'id'                        => "", // todo: purpose unclear - what does it do?
             CommandInterface::DATA     => $workload
        ]);

        $promise = $this->blockingAction(
            $command,
            "JOB_CREATED",
            function (CommandInterface $submitCmd, CommandInterface $createdCmd) use ($workload, $priority) {
                $task = new Task(
                    $submitCmd->get('function_name'),
                    $workload,
                    $createdCmd->get('job_handle'),
                    $priority
                );

                $this->tasks[$task->getHandle()] = $task;
                $this->emit("task-submitted", [$task, $this]);

                return $task;
            }
        );

        return $promise;
    }

    public function setOption($option)
    {
        if ($option != self::OPTION_FORWARD_EXCEPTIONS) {
            throw new ProtocolException("Unsupported option $option");
        }

        $command = $this->getCommandFactory()->create('OPTION_REQ', [
            'option_name' => $option
        ]);

        return $this->blockingAction($command, 'OPTION_RES', function (CommandInterface $req, CommandInterface $res) {
            $option = $req->get('option_name');
            $success = $option == $res->get('option_name');

            if (!$success) {
                throw new ProtocolException("Failed to set option. Server responded with different option");
            }

            $this->emit("option", [$option, $this]);

            return $option;
        });
    }

    public function getStatus($task)
    {
        $handle = $task instanceof Task ? $task->getHandle() : $task;

        $command = $this->getCommandFactory()->create('GET_STATUS', [
            'job_handle' => $handle
        ]);

        return $this->blockingAction(
            $command,
            "STATUS_RES",
            function (CommandInterface $req, CommandInterface $res) use ($handle) {
                if ($req->get('job_handle') != $res->get('job_handle')) {
                    throw new ProtocolException("Job handle of returned STATUS_RES does not match the requested one");
                }

                $task = isset($this->tasks[$handle]) ? $this->tasks[$handle] : new UnknownTask($handle);

                $event = new TaskStatusEvent(
                    $task,
                    $res->get('status'),
                    $res->get('running_status'),
                    $res->get('complete_numerator'),
                    $res->get('complete_denominator')
                );

                if (!$task instanceof UnknownTask) {
                    $task->emit("status", [$event, $this]);
                }

                $this->emit("status", [$event, $this]);

                return $event;
            }
        );
    }

    protected function handleWorkEvent(CommandInterface $command)
    {
        if (!isset($this->tasks[$command->get('job_handle')])) {
            throw new ProtocolException("Unexpected $command. Task unknown");
        }

        $task = $this->tasks[$command->get('job_handle')];

        switch ($command->getName()) {
            case "WORK_COMPLETE":
                $task->emit('complete', [
                    new TaskDataEvent(
                        $task, 
                        $command->get(CommandInterface::DATA)
                    ), 
                    $this
                ]);
                break;
            case "WORK_STATUS":
                $task->emit('status', [
                    new TaskStatusEvent(
                        $task,
                        true,   // known
                        true,   // running
                        $command->get('complete_numerator'),
                        $command->get('complete_denominator')
                    ),
                    $this
                ]);
                break;
            case "WORK_FAIL":
                $task->emit('failure', [new TaskEvent($task), $this]);
                break;
            case "WORK_EXCEPTION":
                $task->emit('exception', [new TaskDataEvent($task, $command->get(CommandInterface::DATA)), $this]);
                break;
            case "WORK_DATA":
                $task->emit('data', [new TaskDataEvent($task, $command->get(CommandInterface::DATA)), $this]);
                break;
            case "WORK_WARNING":
                $task->emit('warning', [new TaskDataEvent($task, $command->get(CommandInterface::DATA)), $this]);
                break;
            // @codeCoverageIgnoreStart
            // internal safe guard, cannot be tested
            default:
                throw new ProtocolException("Unknown work event $command");
            // @codeCoverageIgnoreEnd
        }
    }

}
