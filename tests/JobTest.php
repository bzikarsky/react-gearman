<?php

use Zikarsky\React\Gearman\Job;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use React\Promise\Deferred;


class JobTest extends PHPUnit_Framework_TestCase
{

    protected $worker;
    protected $job;

    public function setUp()
    {
        $this->markTestSkipped();
        $this->worker = $this->createMock('\Zikarsky\React\Gearman\Worker', [], [], '', false);
        $this->job = new Job($this->worker, "foo-function", "bar-handle", "workload");
    }

    public function testFromCommandConstructor()
    {
        $command = $this->createMock('\Zikarsky\React\Gearman\Command\Binary\CommandInterface');
        $command->expects($this->any())->method('getName')->will($this->returnValue('JOB_ASSIGN'));
        $command->expects($this->exactly(3))->method('get')->will($this->returnValueMap([
            ['function_name', null, 'foo-function'],
            ['job_handle', null, 'bar-handle'],
            [CommandInterface::DATA, null, 'workload']
        ]));

        $this->assertEquals($this->job, Job::fromCommand($command, $this->worker));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInvalidFromCommandConstructor()
    {
        $command = $this->createMock('\Zikarsky\React\Gearman\Command\Binary\CommandInterface');
        $command->expects($this->any())->method('getName')->will($this->returnValue('NO_JOB'));

        Job::fromCommand($command, $this->worker);
    }

    public function testGetFunction()
    {
        $this->assertEquals("foo-function", $this->job->getFunction());
    }

    public function testGetHandle()
    {
        $this->assertEquals("bar-handle", $this->job->getHandle());
    }

    public function testGetWorkload()
    {
        $this->assertEquals("workload", $this->job->getWorkload());
    }

    public function testSendStatus()
    {
        $this->worker->expects($this->once())
            ->method('sendJobStatus')
            ->with($this->job, 10, 100)
            ->will($this->returnValue((new Deferred())->promise()));

        $this->assertInstanceOf('\React\Promise\Promise', $this->job->sendStatus(10, 100));
    }

    public function testSendData()
    {
        $this->worker->expects($this->once())
            ->method('sendJobData')
            ->with($this->job, "so much data!")
            ->will($this->returnValue((new Deferred())->promise()));

        $this->assertInstanceOf('\React\Promise\Promise', $this->job->sendData("so much data!"));
    }

    public function testSendWarning()
    {
        $this->worker->expects($this->once())
            ->method('sendJobWarning')
            ->with($this->job, "ALERT! There is an intruder!")
            ->will($this->returnValue((new Deferred())->promise()));

        $this->assertInstanceOf('\React\Promise\Promise', $this->job->sendWarning("ALERT! There is an intruder!"));
    }
}
