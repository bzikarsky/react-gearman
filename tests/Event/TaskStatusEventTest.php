<?php

class TaskStatusEventTest extends PHPUnit_Framework_TestCase
{

    public function testPercentage()
    {
        $e = new \Zikarsky\React\Gearman\Event\TaskStatusEvent(
            $this->getMock('\Zikarsky\React\Gearman\TaskInterface'),
            1,
            1,
            5,
            10
        );

        $this->assertEquals(5/10, $e->getCompletionPercentage());
    }

}
