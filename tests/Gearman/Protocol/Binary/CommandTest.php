<?php

use Gearman\Protocol\Binary\CommandType;
use Gearman\Protocol\Binary\Command;
use Gearman\Protocol\Binary\CommandInterface;

class CommandTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var CommandType
     */
    protected $type;
    protected $data;
    protected $command;

    public function setUp()
    {
        $this->type = new CommandType("TEST", 1, ["arg1", "arg2"]);
        $this->data = ["arg1" => 1];
        $this->magic = CommandInterface::MAGIC_REQUEST;
        $this->command = $this->testCreate();
    }

    public function testCreate()
    {
        return new Command($this->type, $this->data, $this->magic);
    }


    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateInvalidData()
    {
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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidArgumentSet()
    {
        $this->command->set("invalid", "test");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidArgumentGet()
    {
        $this->command->get("invalid");
    }


    /**
     * @depends testCreate
     */
    public function testToString(Command $cmd)
    {
        $this->assertEquals("TEST(1)[arg1=1&arg2=NULL]", (string) $this->command);

        $this->command->set("arg2", "abc");
        $this->assertEquals("TEST(1)[arg1=1&arg2=abc]", (string) $this->command);
    }


}
