<?php

namespace Zikarsky\React\Gearman\Command\Binary;

class DefaultCommandFactory extends CommandFactory
{
    public function __construct()
    {
        $this->addType(new CommandType("CAN_DO", 1, ['function_name']));
        $this->addType(new CommandType("CANT_DO", 2, ['function_name']));
        $this->addType(new CommandType("RESET_ABILITIES", 3, []));
        $this->addType(new CommandType("PRE_SLEEP", 4, []));
        $this->addType(new CommandType("NOOP", 6, []));
        $this->addType(new CommandType("SUBMIT_JOB", 7, ['function_name', 'id', Command::DATA]));
        $this->addType(new CommandType("JOB_CREATED", 8, ['job_handle']));
        $this->addType(new CommandType("GRAB_JOB", 9, []));
        $this->addType(new CommandType("NO_JOB", 10, []));
        $this->addType(new CommandType("JOB_ASSIGN", 11, ['job_handle', 'function_name', Command::DATA]));
        $this->addType(new CommandType("WORK_STATUS", 12, ['job_handle', 'complete_numerator', 'complete_denominator']));
        $this->addType(new CommandType("WORK_COMPLETE", 13, ['job_handle', Command::DATA]));
        $this->addType(new CommandType("WORK_FAIL", 14, ['job_handle']));
        $this->addType(new CommandType("GET_STATUS", 15, ['job_handle']));
        $this->addType(new CommandType("ECHO_REQ", 16, [Command::DATA]));
        $this->addType(new CommandType("ECHO_RES", 17, [Command::DATA]));
        $this->addType(new CommandType("SUBMIT_JOB_BG", 18, ['function_name', 'id', Command::DATA]));
        $this->addType(new CommandType("ERROR", 19, ['code', 'message']));
        $this->addType(new CommandType("STATUS_RES", 20, ['job_handle', 'status', 'running_status', 'complete_numerator', 'complete_denominator']));
        $this->addType(new CommandType("SUBMIT_JOB_HIGH", 21, ['function_name', 'id', Command::DATA]));
        $this->addType(new CommandType("SET_CLIENT_ID", 22, ['worker_id']));
        $this->addType(new CommandType("CAN_DO_TIMEOUT", 23, ['function_name', 'timeout']));
        $this->addType(new CommandType("WORK_EXCEPTION", 25, ['job_handle', Command::DATA]));
        $this->addType(new CommandType("OPTION_REQ", 26, ['option_name']));
        $this->addType(new CommandType("OPTION_RES", 27, ['option_name']));
        $this->addType(new CommandType("WORK_DATA", 28, ['job_handle', Command::DATA]));
        $this->addType(new CommandType("WORK_WARNING", 29, ['job_handle', Command::DATA]));
        $this->addType(new CommandType("GRAB_JOB_UNIQ", 30, []));
        $this->addType(new CommandType("JOB_ASSIGN_UNIQ", 31, ['job_handle', 'function_name', 'id', Command::DATA]));
        $this->addType(new CommandType("SUBMIT_JOB_HIGH_BG", 32, ['function_name', 'id', Command::DATA]));
        $this->addType(new CommandType("SUBMIT_JOB_LOW", 33, ['function_name', 'id', Command::DATA]));
        $this->addType(new CommandType("SUBMIT_JOB_LOW_BG", 34, ['function_name', 'id', Command::DATA]));

        // Not yet implemented. {@see http://gearman.org/protocol/}
        // $this->addType(new CommandType("ALL_YOURS",             24,  []));

        // This is not currently used and may be removed. {@see http://gearman.org/protocol/}
        // $this->addType(new CommandType("SUBMIT_JOB_SCHED",      35, ['function_name', 'id', 'minute', 'hour', 'day_of_month', 'month', 'day_of_week', Command::DATA]));

        // This is not currently used and may be removed. {@see http://gearman.org/protocol/}
        // $this->addType(new CommandType("SUBMIT_JOB_EPOCH",      36, ['function_name', 'id', 'epoch', Command::DATA]));
    }
}
