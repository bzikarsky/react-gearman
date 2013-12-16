<?php

class TaskStatusEventTest extends PHPUnit_Framework_TestCase
{

    public function testPercentage()
    {
        $e = new \Gearman\Async\Event\TaskStatusEvent(
            $this->getMock('\Gearman\Async\TaskInterface'),
            1,
            1,
            5,
            10
        );

        $this->assertEquals(5/10, $e->getCompletionPercentage());
    }

}
