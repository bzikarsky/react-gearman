<?php

class TaskStatusEventTest extends \PHPUnit\Framework\TestCase
{
    public function testPercentage()
    {
        $e = new \Zikarsky\React\Gearman\Event\TaskStatusEvent(
            $this->createMock('\Zikarsky\React\Gearman\TaskInterface'),
            1,
            1,
            5,
            10
        );

        $this->assertEquals(5/10, $e->getCompletionPercentage());
    }
}
