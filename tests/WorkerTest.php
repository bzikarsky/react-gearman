<?php

use Zikarsky\React\Gearman;
use Zikarsky\React\Gearman\Command\Binary\Command;
use Zikarsky\React\Gearman\Command\Binary\CommandFactory;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandType;
use Zikarsky\React\Gearman\Protocol\Connection;

class WorkerTest extends \PHPUnit\Framework\TestCase
{
    protected function getConnection()
    {
        $fac = new Gearman\Command\Binary\DefaultCommandFactory();

        $stream = $this->getMockBuilder(\React\Stream\Stream::class)
            ->setMethods(['write', 'close', 'getBuffer'])
            ->disableOriginalConstructor()
            ->getMock();
        $buffer = $this->createMock(\React\Stream\Buffer::class);
        $stream->expects($this->any())->method('getBuffer')->willReturn($buffer);
        return new Connection($stream, $fac);
    }

    protected function getWorkerMock($mockedMethods = [], $uniq = false)
    {
        $connection = $this->getConnection();
        return [
            $this->getMockBuilder(Gearman\Worker::class)
                ->setMethods($mockedMethods)
                ->setConstructorArgs([$connection, $uniq])
                ->getMock(),
            $connection
        ];
    }

    public function testEventReceiverNOOP()
    {
        list($worker, $connection) = $this->getWorkerMock(['grabJob']);
        $worker->expects($this->once())->method('grabJob');
        /**
         * @var $connection Gearman\Protocol\Connection
         */
        $connection->emit('NOOP');
    }

    public function testEventReceiverNO_JOB()
    {
        list($worker, $connection) = $this->getWorkerMock(['handleNoJob']);
        $worker->expects($this->once())->method('handleNoJob');
        /**
         * @var $connection Gearman\Protocol\Connection
         */
        $connection->emit('NO_JOB');
    }

    public function testEventReceiverJOB_ASSIGN()
    {
        list($worker, $connection) = $this->getWorkerMock(['handleJob']);
        $worker->expects($this->once())->method('handleJob');
        /**
         * @var $connection Gearman\Protocol\Connection
         */
        $type = new CommandType("TEST", 1, ["arg1", "arg2"]);
        $data = ["arg1" => 1];
        $magic = CommandInterface::MAGIC_REQUEST;
        $command = new Command($type, $data, $magic);

        $connection->emit('JOB_ASSIGN', [$command]);
    }

    public function testExecute()
    {
        list($worker, $connection) = $this->getWorkerMock(['send']);

        /**
         * @var PHPUnit_Framework_MockObject_MockObject $worker
         */
        /*
         * Called:
         * - CAN_DO
         * - GRAB_JOB 2x
         * - WORK_COMPLETE
         */
        $worker->expects($this->exactly(4))->method('send')->will($this->returnValue(\React\Promise\resolve()));

        /**
         * @var Gearman\Worker $worker
         */
        $callbackWasCalled = false;
        /**
         * @var Gearman\JobInterface $job
         */
        $job = null;
        $worker->register('foo', function ($_job) use (&$callbackWasCalled, &$job) {
            $this->assertEquals(null, $_job->getId());
            $callbackWasCalled = true;
            $job = $_job;
        });
        $type = new CommandType("JOB_ASSIGN", 11, ['job_handle', 'function_name', Command::DATA]);
        $data = [
            'job_handle' => 1,
            'function_name' => 'foo'
        ];
        $command = new Command($type, $data, CommandInterface::MAGIC_REQUEST);
        /**
         * @var $connection Gearman\Protocol\Connection
         */
        $connection->emit('JOB_ASSIGN', [$command]);

        $this->assertTrue($callbackWasCalled);
        $this->assertEquals(1, $worker->getInflightRequests());

        $job->complete("");
        // Complete decreases inflight requests but grabJob is triggered and this increases them again
        $this->assertEquals(1, $worker->getInflightRequests());
    }

    public function testExecuteUniq()
    {
        list($worker, $connection) = $this->getWorkerMock(['send'], true);

        /**
         * @var PHPUnit_Framework_MockObject_MockObject $worker
         */
        /*
         * Called:
         * - CAN_DO
         * - GRAB_JOB_UNIQ 2x
         * - WORK_COMPLETE
         */
        $worker->expects($this->exactly(4))->method('send')->will($this->returnValue(\React\Promise\resolve()));

        /**
         * @var Gearman\Worker $worker
         */
        $callbackWasCalled = false;
        /**
         * @var Gearman\JobInterface $job
         */
        $job = null;
        $worker->register('foo', function ($_job) use (&$callbackWasCalled, &$job) {
            $this->assertEquals('deadbeef', $_job->getId());

            $callbackWasCalled = true;
            $job = $_job;
        });
        $type = new CommandType("JOB_ASSIGN_UNIQ", 11, ['job_handle', 'function_name', 'id', Command::DATA]);
        $data = [
            'job_handle' => 1,
            'function_name' => 'foo',
            'id' => 'deadbeef'
        ];
        $command = new Command($type, $data, CommandInterface::MAGIC_REQUEST);
        /**
         * @var $connection Gearman\Protocol\Connection
         */
        $connection->emit('JOB_ASSIGN_UNIQ', [$command]);

        $this->assertTrue($callbackWasCalled);
        $this->assertEquals(1, $worker->getInflightRequests());

        $job->complete("");

        // Complete decreases inflight requests but grabJob is triggered and this increases them again
        $this->assertEquals(1, $worker->getInflightRequests());
    }

    public function testMaxInflight()
    {
        list($worker, $connection) = $this->getWorkerMock(['send', 'grabJobSend']);

        /**
         * @var PHPUnit_Framework_MockObject_MockObject $worker
         */
        $worker->expects($this->any())->method('send')->will($this->returnValue(\React\Promise\resolve()));
        $worker->expects($this->exactly(3))->method('grabJobSend');

        /**
         * @var Gearman\Worker $worker
         */
        $worker->setMaxParallelRequests(3);
        $callbackWasCalled = 0;
        /**
         * @var Gearman\JobInterface $job
         */
        $jobs = [];
        $worker->register('foo', function ($job) use (&$callbackWasCalled, &$jobs) {
            $callbackWasCalled++;
            $jobs[] = $job;
        });
        $type = new CommandType("JOB_ASSIGN", 11, ['job_handle', 'function_name', Command::DATA]);
        $data = [
            'job_handle' => 1,
            'function_name' => 'foo'
        ];
        $command = new Command($type, $data, CommandInterface::MAGIC_REQUEST);
        /**
         * @var $connection Gearman\Protocol\Connection
         */
        $connection->emit('JOB_ASSIGN', [$command]);
        $connection->emit('JOB_ASSIGN', [$command]);
        $connection->emit('JOB_ASSIGN', [$command]);

        // Last job does not trigger GRAB_JOB as already 3 jobs are ongoing. So in-flight requests are not increased any more
        // Actually this should not happen in a real env as JOB_ASSIGN is only called as a response for GRAB_JOB
        $connection->emit('JOB_ASSIGN', [$command]);

        $this->assertEquals(4, $callbackWasCalled);
        $this->assertEquals(3, $worker->getInflightRequests());

        // Inflight requests are decreased as a result for NO_JOB response on a GRAB_JOB request
        $connection->emit('NO_JOB');
        $connection->emit('NO_JOB');
        $connection->emit('NO_JOB');

        $this->assertEquals(0, $worker->getInflightRequests());
    }

    public function testMaxInflightLimitReached()
    {
        list($worker, $connection) = $this->getWorkerMock(['send', 'grabJobSend']);

        /**
         * @var PHPUnit_Framework_MockObject_MockObject $worker
         */
        $worker->expects($this->any())->method('send')->will($this->returnValue(\React\Promise\resolve()));
        $worker->expects($this->exactly(6))->method('grabJobSend');

        /**
         * @var Gearman\Worker $worker
         */
        $worker->setMaxParallelRequests(3);
        $callbackWasCalled = 0;
        /**
         * @var Gearman\JobInterface $job
         */
        $jobs = [];
        $worker->register('foo', function ($job) use (&$callbackWasCalled, &$jobs) {
            $callbackWasCalled++;
            $jobs[] = $job;
        });
        $type = new CommandType("JOB_ASSIGN", 11, ['job_handle', 'function_name', Command::DATA]);
        $data = [
            'job_handle' => 1,
            'function_name' => 'foo'
        ];
        $command = new Command($type, $data, CommandInterface::MAGIC_REQUEST);
        /**
         * @var $connection Gearman\Protocol\Connection
         */
        $connection->emit('JOB_ASSIGN', [$command]);
        $connection->emit('JOB_ASSIGN', [$command]);
        $connection->emit('JOB_ASSIGN', [$command]);

        // Last job does not trigger GRAB_JOB as already 3 jobs are ongoing. So in-flight requests are not increased any more
        // Actually this should not happen in a real env as JOB_ASSIGN is only called as a response for GRAB_JOB
        $connection->emit('JOB_ASSIGN', [$command]);

        $job = array_pop($jobs);
        $job->complete();
        $job = array_pop($jobs);
        $job->complete();
        $job = array_pop($jobs);
        $job->complete();

        $this->assertEquals(4, $callbackWasCalled);
        $this->assertEquals(3, $worker->getInflightRequests());

        // Inflight requests are decreased as a result for NO_JOB response on a GRAB_JOB request
        $connection->emit('NO_JOB');
        $connection->emit('NO_JOB');
        $connection->emit('NO_JOB');

        $this->assertEquals(0, $worker->getInflightRequests());
    }
}
