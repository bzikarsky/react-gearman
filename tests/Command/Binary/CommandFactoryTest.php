<?php

use Zikarsky\React\Gearman\Command\Binary\CommandFactory;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandType;

class CommandFactoryTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var CommandFactory
     */
    protected $factory;

    /**
     * @var CommandType
     */
    protected $typeA;

    /**
     * @var CommandType
     */
    protected $typeB;

    public function setUp()
    {
        $this->factory = new CommandFactory();

        // todo: Replace by mocks when CommandType is non-trivial
        $this->typeA = new CommandType("test-a", 1, []);
        $this->typeB = new CommandType("test-b", 2, ["abc", "foo"]);
    }

    public function testAddType()
    {
        $this->factory->addType($this->typeA);
        $this->factory->addType($this->typeB);

        return $this->factory;
    }

    /**
     * @depends testAddType
     */
    public function testGetTypeByCode(CommandFactory $factory)
    {
        $this->assertEquals($this->typeA, $factory->getTypeByCode(1));
        $this->assertEquals($this->typeB, $factory->getTypeByCode(2));
    }

    /**
     * @depends testAddType
     * @expectedException InvalidArgumentException
     */
    public function testGetTypeByCodeInvalid(CommandFactory $factory)
    {
        $factory->getTypeByCode(3);
    }

    /**
     * @depends testAddType
     */
    public function testGetTypeByName(CommandFactory $factory)
    {
        $this->assertEquals($this->typeA, $factory->getTypeByName("test-a"));
        $this->assertEquals($this->typeB, $factory->getTypeByName("test-b"));
    }

    /**
     * @depends testAddType
     * @expectedException InvalidArgumentException
     */
    public function testGetTypeByNameInvalid(CommandFactory $factory)
    {
        $factory->getTypeByName("test-c");
    }

    /**
     * @depends testAddType
     */
    public function testGetType(CommandFactory $factory)
    {
        $this->assertEquals($this->typeA, $factory->getType("test-a"));
        $this->assertEquals($this->typeB, $factory->getType("test-b"));

        $this->assertEquals($this->typeA, $factory->getType(1));
        $this->assertEquals($this->typeB, $factory->getType(2));
    }

    /**
     * @depends testAddType
     */
    public function testCreate(CommandFactory $factory)
    {
        $command = $factory->create(1, [], CommandInterface::MAGIC_REQUEST);
        $this->assertInstanceOf('\Zikarsky\React\Gearman\Command\Binary\CommandInterface', $command);
        $this->assertEquals($this->typeA->getName(), $command->getName());
        $this->assertEquals($this->typeA->getType(), $command->getType());
        $this->assertEquals(CommandInterface::MAGIC_REQUEST, $command->getMagic());
        $this->assertEquals([], $command->getAll());

        $command = $factory->create(2, ["abc" => "def", "foo" => 3], CommandInterface::MAGIC_RESPONSE);
        $this->assertInstanceOf('\Zikarsky\React\Gearman\Command\Binary\CommandInterface', $command);
        $this->assertEquals($this->typeB->getName(), $command->getName());
        $this->assertEquals($this->typeB->getType(), $command->getType());
        $this->assertEquals(CommandInterface::MAGIC_RESPONSE, $command->getMagic());
        $this->assertEquals(["abc" => "def", "foo" => 3], $command->getAll());
    }

    /**
     * @depends testAddType
     * @expectedException InvalidArgumentException
     */
    public function testCreateInvalidType($factory)
    {
        $factory->create(3, [], CommandInterface::MAGIC_REQUEST);
    }

    /**
     * @depends testAddType
     * @expectedException InvalidArgumentException
     */
    public function testCreateInvalidData($factory)
    {
        $factory->create(1, ["test" => "b"], CommandInterface::MAGIC_REQUEST);
    }
}
