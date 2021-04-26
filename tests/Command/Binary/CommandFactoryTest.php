<?php

use Zikarsky\React\Gearman\Command\Binary\CommandFactory as CommandFactoryAlias;
use Zikarsky\React\Gearman\Command\Binary\CommandInterface;
use Zikarsky\React\Gearman\Command\Binary\CommandType;

class CommandFactoryTest extends \PHPUnit\Framework\TestCase
{
    protected CommandFactoryAlias $factory;
    protected CommandType $typeA;
    protected CommandType $typeB;

    public function setUp(): void
    {
        $this->factory = new CommandFactoryAlias();

        // todo: Replace by mocks when CommandType is non-trivial
        $this->typeA = new CommandType("test-a", 1, []);
        $this->typeB = new CommandType("test-b", 2, ["abc", "foo"]);
    }

    public function testAddType()
    {
        $this->expectNotToPerformAssertions();

        $this->factory->addType($this->typeA);
        $this->factory->addType($this->typeB);

        return $this->factory;
    }

    /**
     * @depends testAddType
     */
    public function testGetTypeByCode(CommandFactoryAlias $factory)
    {
        $this->assertEquals($this->typeA, $factory->getTypeByCode(1));
        $this->assertEquals($this->typeB, $factory->getTypeByCode(2));
    }

    /**
     * @depends testAddType
     *
     */
    public function testGetTypeByCodeInvalid(CommandFactoryAlias $factory)
    {
        $this->expectException(InvalidArgumentException::class);
        $factory->getTypeByCode(3);
    }

    /**
     * @depends testAddType
     */
    public function testGetTypeByName(CommandFactoryAlias $factory)
    {
        $this->assertEquals($this->typeA, $factory->getTypeByName("test-a"));
        $this->assertEquals($this->typeB, $factory->getTypeByName("test-b"));
    }

    /**
     * @depends testAddType
     *
     */
    public function testGetTypeByNameInvalid(CommandFactoryAlias $factory)
    {
        $this->expectException(InvalidArgumentException::class);
        $factory->getTypeByName("test-c");
    }

    /**
     * @depends testAddType
     */
    public function testGetType(CommandFactoryAlias $factory)
    {
        $this->assertEquals($this->typeA, $factory->getType("test-a"));
        $this->assertEquals($this->typeB, $factory->getType("test-b"));

        $this->assertEquals($this->typeA, $factory->getType(1));
        $this->assertEquals($this->typeB, $factory->getType(2));
    }

    /**
     * @depends testAddType
     */
    public function testCreate(CommandFactoryAlias $factory)
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
     *
     */
    public function testCreateInvalidType($factory)
    {
        $this->expectException(InvalidArgumentException::class);
        $factory->create(3, [], CommandInterface::MAGIC_REQUEST);
    }

    /**
     * @depends testAddType
     *
     */
    public function testCreateInvalidData($factory)
    {
        $this->expectException(InvalidArgumentException::class);
        $factory->create(1, ["test" => "b"], CommandInterface::MAGIC_REQUEST);
    }
}
