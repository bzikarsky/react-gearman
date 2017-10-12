<?php

class DefaultCommandFactoryTest extends PHPUnit_Framework_TestCase
{
    private static $maxTypeId = 36;
    private static $minTypeId = 1;
    private static $missingTypes = [
        5 /* unused */,
        24 /* not yet implemented */,
        35 /* unused */,
        36 /* unused */
    ];

    public function testCommandExists()
    {
        $f = new \Zikarsky\React\Gearman\Command\Binary\DefaultCommandFactory();
        for ($i=self::$minTypeId; $i<(self::$maxTypeId+1); $i++) {
            if (in_array($i, self::$missingTypes)) {
                continue;
            }
            $f->create($i);
        }
    }

    public function testWorkFailCommand()
    {
        $jobHandle = 'H:toto:42';
        $length = strlen($jobHandle);
        $type = 14;
        $rawCommand = "\x00RES".pack('N', $type).pack('N', $length).$jobHandle;

        $factory = new \Zikarsky\React\Gearman\Command\Binary\DefaultCommandFactory();

        $readBuffer = new \Zikarsky\React\Gearman\Command\Binary\ReadBuffer($factory);

        $this->assertEquals(1, $readBuffer->push($rawCommand));
        $command = $readBuffer->shift();
        $this->assertEquals($type, $command->getType());
        $this->assertEquals($jobHandle, $command->get('job_handle'), "Job handles are not the equals.");
    }
}
