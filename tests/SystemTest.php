<?php

use Zikarsky\React\Gearman\ClientInterface;
use Zikarsky\React\Gearman\DuplicateJobException;
use Zikarsky\React\Gearman\Event\TaskDataEvent;
use Zikarsky\React\Gearman\Event\TaskEvent;
use Zikarsky\React\Gearman\JobInterface;
use Zikarsky\React\Gearman\TaskInterface;
use Zikarsky\React\Gearman\WorkerInterface;

/**
 * Class SystemTest
 * @group system
 */
class SystemTest extends PHPUnit_Framework_TestCase
{

    protected function asyncTest(callable $coroutine)
    {
        \Amp\Loop::set((new \Amp\Loop\DriverFactory)->create());
        gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop
        return \Amp\Promise\wait(\Amp\call($coroutine));
    }

    protected function getFactory()
    {
        $loop = \Amp\ReactAdapter\ReactAdapter::get();
        return new \Zikarsky\React\Gearman\Factory($loop);
    }

    protected function getWorkerAndClient()
    {
        $factory = $this->getFactory();

        /**
         * @var ClientInterface $client
         * @var WorkerInterface $worker
         */
        $client = yield $factory->createClient('127.0.0.1', 4730);
        $worker = yield $factory->createWorker('127.0.0.1', 4730);
        return [$client, $worker];
    }

    protected function getTaskPromise(TaskInterface $task, callable $onTask)
    {
        $deferred = new \Amp\Deferred();

        $watcher = \Amp\Loop::delay(200, function () use ($deferred, $task) {
            $deferred->fail(new Exception("Job timed out: {$task->getWorkload()}"));
        });

        $task->on('complete', function (TaskDataEvent $event, ClientInterface $client) use ($deferred, $onTask, $watcher) {
            \Amp\Loop::cancel($watcher);
            try {
                $onTask($event, $client);
                $deferred->resolve();
            } catch (Throwable $e) {
                $deferred->fail($e);
            }
        });

        $task->on('exception', function (TaskDataEvent $event, ClientInterface $client) use ($deferred, $onTask, $watcher) {
            \Amp\Loop::cancel($watcher);
            $deferred->fail(new Exception($event->getData()));
        });

        $task->on('failure', function (TaskEvent $event, ClientInterface $client) use ($deferred, $onTask, $watcher) {
            \Amp\Loop::cancel($watcher);
            $deferred->fail(new Exception("N/A"));
        });

        return $deferred->promise();
    }

    public function testSubmitAndWork()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            $workerCalled = false;
            $responseReceived = false;
            yield $worker->register('test', function (JobInterface $job) use (&$workerCalled) {
                $job->complete($job->getWorkload());
                $workerCalled = true;
            });

            /**
             * @var TaskInterface $task
             */
            $task = yield $client->submit('test', 'TestData');

            yield $this->getTaskPromise($task, function (TaskDataEvent $event, ClientInterface $client) use (&$responseReceived) {
                $responseReceived = true;
                $this->assertEquals('TestData', $event->getData());
                $client->disconnect();
            });

            $this->assertTrue($workerCalled);
            $this->assertTrue($responseReceived);
        });
    }

    public function testSubmitBackgroundAndWork()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            $workerCalled = false;

            $task = yield $client->submitBackground('test', 'TestData');
            $this->assertInstanceOf(TaskInterface::class, $task);

            $deferred = new \Amp\Deferred();

            yield $worker->register('test', function (JobInterface $job) use (&$workerCalled, $deferred) {
                $job->complete($job->getWorkload());
                $workerCalled = true;
                $this->assertEquals('TestData', $job->getWorkload());
                $deferred->resolve();
            });

            \Amp\Loop::delay(100, function () use ($deferred) {
                $deferred->fail(new Exception("Job timed out"));
            });

            yield $deferred->promise();
            $this->assertTrue($workerCalled);
        });
    }

    public function testSubmitWithUniqueIds()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            /**
             * @var TaskInterface $task1
             * @var TaskInterface $task2
             * @var TaskInterface $task3
             */
            $task1 = yield $client->submit('test', 'TestData1', TaskInterface::PRIORITY_NORMAL, '1');
            try {
                yield $client->submit('test', 'TestData1a', TaskInterface::PRIORITY_NORMAL, '1');
                $this->fail("DuplicateJobException not thrown");
            }
            catch (DuplicateJobException $e) {
                // Expected
            }
            $task3 = yield $client->submit('test', 'TestData2', TaskInterface::PRIORITY_NORMAL, '2');

            $taskPromise1 = $this->getTaskPromise($task1, function (TaskDataEvent $event, ClientInterface $client) use (&$responseReceived) {
                $this->assertEquals('TestData1', $event->getData());
            });
            $taskPromise3 = $this->getTaskPromise($task3, function (TaskDataEvent $event, ClientInterface $client) use (&$responseReceived) {
                $this->assertEquals('TestData2', $event->getData());
            });

            $deferred = new \Amp\Deferred();

            $jobCalls = [];
            yield $worker->register('test', function (JobInterface $job) use (&$jobCalls, $deferred) {
                $job->complete($job->getWorkload());
                $jobCalls[] = $job->getWorkload();
                if (count($jobCalls) == 2) {
                    $deferred->resolve();
                }
            });

            yield $taskPromise1;
            yield $taskPromise3;

            $this->assertEquals([
                'TestData1',
                'TestData2',
            ], $jobCalls);
        });
    }

    public function testSubmitBackgroundWithUniqueIds()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            yield $client->submitBackground('test', 'TestData1', TaskInterface::PRIORITY_NORMAL, '1b');
            yield $client->submitBackground('test', 'TestData1a', TaskInterface::PRIORITY_NORMAL, '1b');
            yield $client->submitBackground('test', 'TestData2', TaskInterface::PRIORITY_NORMAL, '2b');

            $deferred = new \Amp\Deferred();

            $jobCalls = [];
            yield $worker->register('test', function (JobInterface $job) use (&$jobCalls, $deferred) {
                $job->complete($job->getWorkload());
                $jobCalls[] = $job->getWorkload();
                if (count($jobCalls) == 2) {
                    $deferred->resolve();
                }
            });

            \Amp\Loop::delay(100, function () use ($deferred) {
                $deferred->fail(new Exception("Job timed out"));
            });

            yield $deferred->promise();
            $this->assertEquals([
                'TestData1',
                'TestData2',
            ], $jobCalls);
        });
    }

    public function testSubmitWithPriorities()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            yield $client->submit('test', 'TestData3', TaskInterface::PRIORITY_LOW, '3');
            try {
                yield $client->submit('test', 'TestData3a', TaskInterface::PRIORITY_LOW, '3');
            }
            catch (Exception $e) {

            }
            yield $client->submit('test', 'TestData4', TaskInterface::PRIORITY_HIGH, '4');

            $deferred = new \Amp\Deferred();

            $jobCalls = [];
            yield $worker->register('test', function (JobInterface $job) use (&$jobCalls, $deferred) {
                $job->complete($job->getWorkload());
                $jobCalls[] = $job->getWorkload();
                if (count($jobCalls) == 2) {
                    $deferred->resolve();
                }
            });

            \Amp\Loop::delay(100, function () use ($deferred) {
                $deferred->fail(new Exception("Job timed out"));
            });

            yield $deferred->promise();
            $this->assertEquals([
                'TestData4',
                'TestData3',
            ], $jobCalls);
        });
    }

    public function testSubmitBackgroundWithPriorities()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            yield $client->submitBackground('test', 'TestData3', TaskInterface::PRIORITY_LOW, '3b');
            yield $client->submitBackground('test', 'TestData3a', TaskInterface::PRIORITY_LOW, '3b');
            yield $client->submitBackground('test', 'TestData4', TaskInterface::PRIORITY_HIGH, '4b');

            $deferred = new \Amp\Deferred();

            $jobCalls = [];
            yield $worker->register('test', function (JobInterface $job) use (&$jobCalls, $deferred) {
                $job->complete($job->getWorkload());
                $jobCalls[] = $job->getWorkload();
                if (count($jobCalls) == 2) {
                    $deferred->resolve();
                }
            });

            \Amp\Loop::delay(100, function () use ($deferred) {
                $deferred->fail(new Exception("Job timed out"));
            });

            yield $deferred->promise();
            $this->assertEquals([
                'TestData4',
                'TestData3',
            ], $jobCalls);
        });
    }

    public function testProgress()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            /**
             * @var TaskInterface $task1
             */
            $task = yield $client->submit('test', 'TestData1', TaskInterface::PRIORITY_NORMAL, '1p');

            $dataReceived = [];
            $task->on('data', function(TaskDataEvent $event, ClientInterface $client) use (&$dataReceived) {
                $dataReceived[] = $event->getData();
            });

            $taskPromise1 = $this->getTaskPromise($task, function (TaskDataEvent $event, ClientInterface $client) use (&$responseReceived) {
                $this->assertEquals('TestData1', $event->getData());
            });

            $deferred = new \Amp\Deferred();

            $jobCalls = [];
            yield $worker->register('test', function (JobInterface $job) use (&$jobCalls, $deferred) {
                $job->sendData("Some data");
                $job->complete($job->getWorkload());
                $jobCalls[] = $job->getWorkload();
                $deferred->resolve();
            });

            yield $taskPromise1;

            $this->assertEquals([
                'TestData1'
            ], $jobCalls);
            $this->assertEquals([
                'Some data'
            ], $dataReceived);
        });
    }

    public function testException()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            yield $client->setOption('exceptions');
            /**
             * @var TaskInterface $task1
             */
            $task = yield $client->submit('test', 'TestData1', TaskInterface::PRIORITY_NORMAL);

            $taskPromise1 = $this->getTaskPromise($task, function (TaskDataEvent $event, ClientInterface $client) use (&$responseReceived) {
                $this->assertEquals('TestData1', $event->getData());
            });

            yield $worker->register('test', function (JobInterface $job) use (&$jobCalls) {
                $job->fail("Reason");
            });

            try {
                yield $taskPromise1;
                $this->fail("Job did not return with error");
            }
            catch (Exception $e) {
                $this->assertEquals("Reason", $e->getMessage());
            }
        });
    }

    public function testError()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            /**
             * @var TaskInterface $task1
             */
            $task = yield $client->submit('test', 'TestData1', TaskInterface::PRIORITY_NORMAL);

            $taskPromise1 = $this->getTaskPromise($task, function (TaskDataEvent $event, ClientInterface $client) use (&$responseReceived) {
                $this->assertEquals('TestData1', $event->getData());
            });

            yield $worker->register('test', function (JobInterface $job) use (&$jobCalls) {
                $job->fail();
            });

            try {
                yield $taskPromise1;
                $this->fail("Job did not return with error");
            }
            catch (Exception $e) {
                $this->assertEquals("N/A", $e->getMessage());
            }
        });
    }

    public function testWarning()
    {
        $this->asyncTest(function () {
            /**
             * @var ClientInterface $client
             * @var WorkerInterface $worker
             */
            list($client, $worker) = yield from $this->getWorkerAndClient();

            /**
             * @var TaskInterface $task1
             */
            $task = yield $client->submit('test', 'TestData1', TaskInterface::PRIORITY_NORMAL);

            $taskPromise1 = $this->getTaskPromise($task, function (TaskDataEvent $event, ClientInterface $client) use (&$responseReceived) {
                $this->assertEquals('TestData1', $event->getData());
            });

            $warningReceived = null;
            $task->on('warning', function(TaskDataEvent $event, ClientInterface $client) use (&$warningReceived) {
                $warningReceived = $event->getData();
            });

            yield $worker->register('test', function (JobInterface $job) use (&$jobCalls) {
                $job->sendWarning('A warning');
                $job->complete('TestData1');
            });

            yield $taskPromise1;
            $this->assertEquals("A warning", $warningReceived);
        });
    }

}
