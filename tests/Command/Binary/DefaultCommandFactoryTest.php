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

}
