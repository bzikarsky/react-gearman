<?php

use Zikarsky\React\Gearman\Command\Binary\CommandType;
use Zikarsky\React\Gearman\Command\Binary\Command;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;

class CommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CommandType
     */
    protected $type;
    protected $data;
    protected $command;

    public function setUp(): void    {
        $this->type = new CommandType("TEST", 1, ["arg1", "arg2"]);
        $this->data = ["arg1" => 1];
        $this->magic = CommandInterface::MAGIC_REQUEST;
        $this->command = new Command($this->type, $this->data, $this->magic);
    }

    public function testCreateInvalidData()
    {
        $this->expectException(InvalidArgumentException::class);
        return new Command($this->type, ["foo" => "bar"], $this->magic);
    }

    public function testPropertyGetters()
    {
        $cmd = $this->command;

        $this->assertEquals($this->type->getName(), $cmd->getName());
        $this->assertEquals($this->type->getType(), $cmd->getType());
        $this->assertEquals($this->magic, $cmd->getMagic());
    }

    public function testArguments()
    {
        $cmd = $this->command;

        // get
        $this->assertEquals(1, $cmd->get("arg1"));
        $this->assertEquals(1, $cmd->get("arg1", "test"));
        $this->assertEquals(null, $cmd->get("arg2"));
        $this->assertEquals("test", $cmd->get("arg2", "test"));

        // getAll
        $this->assertEquals(["arg1" => 1, "arg2" => null], $cmd->getAll());
        $this->assertEquals(["arg1" => 1, "arg2" => "test"], $cmd->getAll("test"));

        // set
        $cmd->set("arg1", -1);
        $cmd->set("arg2", 2);

        // get after set
        $this->assertEquals(-1, $cmd->get("arg1"));
        $this->assertEquals(-1, $cmd->get("arg1", "test"));
        $this->assertEquals(2, $cmd->get("arg2"));
        $this->assertEquals(2, $cmd->get("arg2", "test"));

        // getAll after set
        $this->assertEquals(["arg1" => -1, "arg2" => 2], $cmd->getAll());
        $this->assertEquals(["arg1" => -1, "arg2" => 2], $cmd->getAll("test"));
    }

    public function testInvalidArgumentSet()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->command->set("invalid", "test");
    }

    public function testInvalidArgumentGet()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->command->get("invalid");
    }


    public function testToString()
    {
        $this->assertEquals("TEST(1)[arg1=1&arg2=NULL]", (string) $this->command);

        $this->command->set("arg2", "abc");
        $this->assertEquals("TEST(1)[arg1=1&arg2=abc]", (string) $this->command);
    }
}
