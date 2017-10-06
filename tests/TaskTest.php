<?php

use Zikarsky\React\Gearman\TaskInterface;

class TaskTest extends PHPUnit_Framework_TestCase
{
    public function testTaskGetters()
    {
        $task = new \Zikarsky\React\Gearman\Task("function", "workload", "handle", TaskInterface::PRIORITY_NORMAL, "");

        $this->assertEquals("function", $task->getFunction());
        $this->assertEquals("workload", $task->getWorkload());
        $this->assertEquals("handle", $task->getHandle());
        $this->assertEquals(TaskInterface::PRIORITY_NORMAL, $task->getPriority());
    }

    public function testUnknownTaskGetters()
    {
        $task = new \Zikarsky\React\Gearman\UnknownTask("handle");

        $this->assertNull($task->getFunction());
        $this->assertNull($task->getWorkload());
        $this->assertEquals("handle", $task->getHandle());
        $this->assertNull($task->getPriority());
    }
}
