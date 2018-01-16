<?php

use Zikarsky\React\Gearman\Client;
use Zikarsky\React\Gearman\TaskInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Event\TaskStatusEvent;
use Zikarsky\React\Gearman\Event\TaskDataEvent;
use Zikarsky\React\Gearman\Event\TaskEvent;
use Zikarsky\React\Gearman\ClientInterface;
use React\Promise\FulfilledPromise;

class ClientTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    protected $connection;
    protected $client;
    protected $factory;
    protected $buffer;

    public function setUp()
    {
        $stream = $this->createMock(\React\Stream\Stream::class);
        $this->buffer = $this->createPartialMock(\React\Stream\Buffer::class, ['isWritable']);
        $stream->expects($this->any())->method('getBuffer')->willReturn($this->buffer);
        $this->factory = new \Zikarsky\React\Gearman\Command\Binary\DefaultCommandFactory();

        $this->connection = $this->getMockBuilder(\Zikarsky\React\Gearman\Protocol\Connection::class)
            ->setMethods(['send', 'close'])
            ->setConstructorArgs([$stream, $this->factory])
            ->getMock();
        $this->connection->expects($this->any())->method('send')->will($this->returnValue(new FulfilledPromise()));
        $this->client = new Client($this->connection);
    }

    public function testCloseEvent()
    {
        // Create a task first
        $promiseTask = null;

        $this->client->submit("test", "data", TaskInterface::PRIORITY_HIGH, '')->then(function ($createdTask) use (&$promiseTask) {
            $promiseTask = $createdTask;
        });
        $this->respond("JOB_CREATED", ["job_handle" => "test.job"]);

        $this->assertNotNull($promiseTask);
        $failedWith = null;
        $promiseTask->on('exception', function (TaskDataEvent $event) use (&$failedWith) {
            $failedWith = $event->getData();
        });

        // Then expect task to fail
        $closeCalled = false;
        $this->client->on("close", function () use (&$closeCalled) {
            $closeCalled = true;
        });

        $this->connection->emit("close");
        $this->assertTrue($closeCalled);
        $this->assertEquals('Lost connection', $failedWith);

        return $this->client;
    }

    public function submissionDataProvider()
    {
        return [
            ["test", "data", TaskInterface::PRIORITY_HIGH, ''],
            ["test", ["test" => "serialize"], TaskInterface::PRIORITY_LOW, ''],
            ["123", "", TaskInterface::PRIORITY_NORMAL, '123'],
        ];
    }

    public function taskCommandProvider()
    {
        return [
            ["WORK_COMPLETE"],
            ["WORK_STATUS"],
            ["WORK_FAIL"],
            ["WORK_EXCEPTION"],
            ["WORK_DATA"],
            ["WORK_WARNING"]
        ];
    }

    /**
     * @dataProvider submissionDataProvider
     */
    public function testSubmit($f, $data, $prio, $uniqueId)
    {
        $this->submit($f, $data, $prio, $uniqueId);
    }

    /**
     * @dataProvider submissionDataProvider
     */
    public function testSubmitBackground($f, $data, $prio, $uniqueId)
    {
        $this->submitBackground($f, $data, $prio, $uniqueId);
    }

    /**
     * @dataProvider taskCommandProvider
     */
    public function testInvalidUnknownTaskCommand($command)
    {
        $called = false;
        $this->client->once('task-unknown', function ($handle, $commandName) use (&$called, $command) {
            $this->assertEquals('unknown', $handle);
            $this->assertEquals($command, $commandName);
            $called = true;
        });

        $this->respond($command, ["job_handle" => "unknown"]);
        $this->assertTrue($called);
    }

    public function testWorkStatusEvent()
    {
        $task = $this->submit("function", "data");
        $event = null;

        $task->on('status', function (TaskStatusEvent $ev) use (&$event) {
            $event = $ev;
        });

        $this->respond("WORK_STATUS", ["job_handle" => "test.job", "complete_numerator" => 5, "complete_denominator" => 10]);

        $this->assertNotNull($event);
        $this->assertEquals($task, $event->getTask());
        $this->assertEquals(5, $event->getCompletionNumerator());
        $this->assertEquals(10, $event->getCompletionDenominator());
    }

    public function testWorkDataEvent()
    {
        $task = $this->submit("function", "data");
        $event = null;

        $task->on('data', function (TaskDataEvent $ev) use (&$event) {
            $event = $ev;
        });

        $this->respond("WORK_DATA", ["job_handle" => "test.job", CommandInterface::DATA => "data"]);

        $this->assertNotNull($event);
        $this->assertEquals($task, $event->getTask());
        $this->assertEquals("data", $event->getData());
    }

    public function testWorkWarningEvent()
    {
        $task = $this->submit("function", "data");
        $event = null;

        $task->on('warning', function (TaskDataEvent $ev) use (&$event) {
            $event = $ev;
        });

        $this->respond("WORK_WARNING", ["job_handle" => "test.job", CommandInterface::DATA => "warning"]);

        $this->assertNotNull($event);
        $this->assertEquals($task, $event->getTask());
        $this->assertEquals("warning", $event->getData());
    }

    public function testWorkFailureEvent()
    {
        $task = $this->submit("function", "data");
        $event = null;

        $task->on('failure', function (TaskEvent $ev) use (&$event) {
            $event = $ev;
        });

        $this->respond("WORK_FAIL", ["job_handle" => "test.job"]);

        $this->assertNotNull($event);
        $this->assertEquals($task, $event->getTask());
    }

    public function testWorkExceptionEvent()
    {
        $task = $this->submit("function", "data");
        $event = null;

        $task->on('exception', function (TaskDataEvent $ev) use (&$event) {
            $event = $ev;
        });

        $this->respond("WORK_EXCEPTION", ["job_handle" => "test.job", CommandInterface::DATA => "exception"]);

        $this->assertNotNull($event);
        $this->assertEquals($task, $event->getTask());
        $this->assertEquals("exception", $event->getData());
    }

    public function testWorkCompleteEvent()
    {
        $task = $this->submit("function", "data");
        $event = null;

        $task->on('complete', function (TaskDataEvent $ev) use (&$event) {
            $event = $ev;
        });

        $this->respond("WORK_COMPLETE", ["job_handle" => "test.job", CommandInterface::DATA => "complete"]);

        $this->assertNotNull($event);
        $this->assertEquals($task, $event->getTask());
        $this->assertEquals("complete", $event->getData());
    }

    public function testPing()
    {
        $pong = false;
        $pongEvent = false;
        $pongData = "invalid";

        $this->connection->expects($this->once())
            ->method('send')
            ->will($this->returnCallback(function ($ping) use (&$pongData) {
                $pongData = $ping->get(CommandInterface::DATA);
                return new FulfilledPromise();
            }));

        $this->client->on('ping', function () use (&$pongEvent) {
            $pongEvent = true;
        });

        $this->client->ping()->then(
            function () use (&$pong) {
                $pong = true;
            }
        );

        $this->respond("ECHO_RES", [CommandInterface::DATA => $pongData]);

        $this->assertTrue($pong);
        $this->assertTrue($pongEvent);
    }

    /**
     * @expectedException \Zikarsky\React\Gearman\Command\Exception
     */
    public function testPingInvalidResponse()
    {
        $this->client->ping();
        $this->respond("ECHO_RES", [CommandInterface::DATA => "invalid"]);
    }

    private function respond($name, array $data = [])
    {
        $this->connection->emit($name, [$this->factory->create($name, $data, CommandInterface::MAGIC_RESPONSE)]);
    }

    private function submit($f, $data, $prio = TaskInterface::PRIORITY_NORMAL, $uniqueId = "")
    {
        $eventTask = null;
        $promiseTask = null;

        $this->client->on("task-submitted", function ($createdTask) use (&$eventTask) {
            $eventTask = $createdTask;
        });

        $this->client->submit($f, $data, $prio, $uniqueId)->then(function ($createdTask) use (&$promiseTask) {
            $promiseTask = $createdTask;
        });
        $this->respond("JOB_CREATED", ["job_handle" => "test.job"]);

        foreach ([$eventTask, $promiseTask] as $task) {
            $this->assertNotNull($task);
            $this->assertEquals("test.job", $task->getHandle());
            $this->assertEquals($f, $task->getFunction());
            $this->assertEquals($data, $task->getWorkload());
            $this->assertEquals($prio, $task->getPriority());
        }

        return $eventTask;
    }

    private function submitBackground($f, $data, $prio = TaskInterface::PRIORITY_NORMAL, $uniqueId = "")
    {
        $eventTask = null;
        $promiseTask = null;

        $this->client->on("task-submitted", function ($createdTask) use (&$eventTask) {
            $eventTask = $createdTask;
        });

        $this->client->submitBackground($f, $data, $prio, $uniqueId)->then(function ($createdTask) use (&$promiseTask) {
            $promiseTask = $createdTask;
        });
        $this->respond("JOB_CREATED", ["job_handle" => "test.job"]);

        foreach ([$eventTask, $promiseTask] as $task) {
            $this->assertInstanceOf(TaskInterface::class, $task);
            $this->assertEquals('test.job', $task->getHandle());
            $this->assertEquals($f, $task->getFunction());
            $this->assertEquals($data, $task->getWorkload());
            $this->assertEquals($prio, $task->getPriority());
        }

        return $eventTask;
    }

    public function testSubmitBackgroundWithLostConnectionBeforeWrite()
    {
        $promiseTask = null;
        $exception = null;

        // Connection is lost before command has been written
        $this->client->submitBackground('func', 'data')->then(function ($createdTask) use (&$promiseTask) {
            $promiseTask = $createdTask;
        }, function ($e) use (&$exception) {
            $exception = $e;
        });
        $this->connection->emit('close');

        $this->assertNull($promiseTask);
        $this->assertInstanceOf(\Zikarsky\React\Gearman\ConnectionLostException::class, $exception);
    }

    public function testSubmitBackgroundWithLostConnectionAfterWrite()
    {
        // Connection is lost after command has been written but no response received
        $promiseTask = null;
        $exception = null;

        $this->client->submitBackground('func', 'data')->then(function ($createdTask) use (&$promiseTask) {
            $promiseTask = $createdTask;
        }, function ($e) use (&$exception) {
            $exception = $e;
        });
        $this->buffer->emit('full-drain');
        $this->connection->emit('close');

        $this->assertNull($promiseTask);
        $this->assertInstanceOf(\Zikarsky\React\Gearman\ConnectionLostException::class, $exception);
    }

    public function testSubmitWithLostConnectionBeforeWrite()
    {
        $eventTask = null;
        $promiseTask = null;
        $exception = null;

        $this->client->submit('func', 'data')->then(function ($createdTask) use (&$promiseTask) {
            $promiseTask = $createdTask;
        }, function ($e) use (&$exception) {
            $exception = $e;
        });
        $this->connection->emit('close');

        $this->assertNull($promiseTask);
        $this->assertInstanceOf(\Zikarsky\React\Gearman\ConnectionLostException::class, $exception);
    }

    public function testSubmitWithLostConnectionAfterWrite()
    {
        // Connection is lost after command has been written but no response received
        $promiseTask = null;
        $exception = null;

        $this->client->submit('func', 'data')->then(function ($createdTask) use (&$promiseTask) {
            $promiseTask = $createdTask;
        }, function ($e) use (&$exception) {
            $exception = $e;
        });
        $this->buffer->emit('full-drain');
        $this->connection->emit('close');

        $this->assertNull($promiseTask);
        $this->assertInstanceOf(\Zikarsky\React\Gearman\ConnectionLostException::class, $exception);
    }

    public function testSetOption()
    {
        $confirmed = false;
        $confirmedEvent = false;
        $option = ClientInterface::OPTION_FORWARD_EXCEPTIONS;

        $this->client->on('option', function () use (&$confirmedEvent) {
            $confirmedEvent = true;
        });

        $this->client->setOption($option)->then(function () use (&$confirmed) {
            $confirmed = true;
        });

        $this->respond("OPTION_RES", ['option_name' => $option]);

        $this->assertTrue($confirmed);
        $this->assertTrue($confirmedEvent);
    }

    /**
     * @expectedException \Zikarsky\React\Gearman\Command\Exception
     */
    public function testSetOptionInvalidResponse()
    {
        $confirmed = false;
        $option = ClientInterface::OPTION_FORWARD_EXCEPTIONS;

        $this->client->setOption($option)->then(function () use (&$confirmed) {
            $confirmed = true;
        });

        $this->respond("OPTION_RES", ['option_name' => "invalid"]);
    }

    /**
     * @expectedException \Zikarsky\React\Gearman\Command\Exception
     */
    public function testSetOptionInvalidOption()
    {
        $this->client->setOption("invalid");
    }

    public function testGetStatus()
    {
        $task = $this->submit("function", "data");

        $taskEvent = false;
        $clientPromise = false;
        $clientEvent = true;

        $this->client->on('status', function (TaskStatusEvent $status) use (&$clientEvent) {
            $clientEvent = true;
        });

        $this->client->getStatus($task)->then(function (TaskStatusEvent $status) use (&$clientPromise) {
            $clientPromise = true;
        });

        $task->on('status', function (TaskStatusEvent $status) use (&$taskEvent, $task) {
            $taskEvent = true;

            $this->assertEquals($task, $status->getTask());
            $this->assertEquals(1, $status->getKnown());
            $this->assertEquals(1, $status->getRunning());
            $this->assertEquals(5, $status->getCompletionNumerator());
            $this->assertEquals(10, $status->getCompletionDenominator());
        });

        $this->respond("STATUS_RES", [
            'job_handle' => 'test.job',
            'status' => 1,
            'running_status' => 1,
            'complete_numerator' => 5,
            'complete_denominator' => 10
        ]);

        $this->assertTrue($taskEvent);
        $this->assertTrue($clientPromise);
        $this->assertTrue($clientEvent);
    }

    public function testGetStatusUnknownJob()
    {
        $clientPromise = false;
        $clientEvent = true;

        $this->client->on('status', function (TaskStatusEvent $status) use (&$clientEvent) {
            $clientEvent = true;
            $this->assertInstanceOf('\Zikarsky\React\Gearman\UnknownTask', $status->getTask());
        });

        $this->client->getStatus('task')->then(function (TaskStatusEvent $status) use (&$clientPromise) {
            $clientPromise = true;
            $this->assertInstanceOf('\Zikarsky\React\Gearman\UnknownTask', $status->getTask());
        });

        $this->respond("STATUS_RES", [
            'job_handle' => 'task',
            'status' => 1,
            'running_status' => 1,
            'complete_numerator' => 5,
            'complete_denominator' => 10
        ]);

        $this->assertTrue($clientPromise);
        $this->assertTrue($clientEvent);
    }

    /**
     * @expectedException \Zikarsky\React\Gearman\Command\Exception
     */
    public function testGetStatusInvalidResponse()
    {
        $this->client->getStatus('task');
        $this->respond("STATUS_RES", ['job_handle' => 'invalid']);
    }

    /**
     * @expectedException \Zikarsky\React\Gearman\Command\Exception
     */
    public function testErrorPacket()
    {
        $this->respond("ERROR", ["message" => "error", "code" => 1]);
    }

    /**
     * @expectedException \Zikarsky\React\Gearman\Command\Exception
     */
    public function testUnhandledEvent()
    {
        $this->connection->emit("unhandled-command", [$this->factory->create("GRAB_JOB")]);
    }

    public function testBlockedSend()
    {
        $pongData = "invalid";
        $log = [];

        $this->connection->expects($this->any())
            ->method('send')
            ->will($this->returnCallback(function ($ping) use (&$pongData) {
                if ($ping->getName() != "ECHO_REQ") {
                    return new FulfilledPromise();
                }
                $pongData = $ping->get(CommandInterface::DATA);
                return new FulfilledPromise();
            }));

        $this->client->ping()->then(function () use (&$log) {
            $log[] = "ping1";
        });
        $this->client->ping()->then(function () use (&$log) {
            $log[] = "ping2";
        });
        $this->client->submit("test")->then(function () use (&$log) {
            $log[] = 'submit';
        });
        $this->client->setOption(ClientInterface::OPTION_FORWARD_EXCEPTIONS)->then(function () use (&$log) {
            $log[] = 'option';
        });

        $expectedLog = [];
        $this->assertEquals($expectedLog, $log);

        $this->respond("ECHO_RES", [CommandInterface::DATA => $pongData]);
        $expectedLog[] = "ping1";
        $this->assertEquals($expectedLog, $log);

        $this->respond("ECHO_RES", [CommandInterface::DATA => $pongData]);
        $expectedLog[] = "ping2";
        $this->assertEquals($expectedLog, $log);

        $this->respond("JOB_CREATED", ['job_handle' => 'test']);
        $expectedLog[] = "submit";
        $this->assertEquals($expectedLog, $log);

        $this->respond("OPTION_RES", ["option_name" => ClientInterface::OPTION_FORWARD_EXCEPTIONS]);
        $expectedLog[] = "option";
        $this->assertEquals($expectedLog, $log);
    }

    public function testDisconnect()
    {
        $this->connection->expects($this->once())
            ->method('close');

        $this->client->disconnect();
    }
}
