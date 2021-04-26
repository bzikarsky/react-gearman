<?php

namespace Zikarsky\React\Gearman\Command\Binary;

use InvalidArgumentException;

/**
 * This command-factory provides can construct all the commands defined in
 * @see http://gearman.org/protocol/
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
class CommandFactory implements CommandFactoryInterface
{

    /**
     * @var CommandType[]
     */
    protected array $typesByName = [];

    /**
     * @var CommandType[]
     */
    protected array $typesByCode = [];

    public function addType(CommandType $type): void
    {
        $this->typesByName[$type->getName()] = $type;
        $this->typesByCode[$type->getType()] = $type;
    }

    /**
     * @param string|int $type
     * @throws InvalidArgumentException
     */
    public function getType($type): CommandType
    {
        return is_string($type)
            ? $this->getTypeByName($type)
            : $this->getTypeByCode($type)
        ;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getTypeByCode(int $code): CommandType
    {
        if (!isset($this->typesByCode[$code])) {
            throw new InvalidArgumentException(__METHOD__  . ' requires $code to be a CommandType identifying integer code');
        }

        return $this->typesByCode[$code];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getTypeByName(string $name): CommandType
    {
        if (!isset($this->typesByName[$name])) {
            throw new InvalidArgumentException(__METHOD__  . " requires $name to be a CommandType identifying name-string");
        }

        return $this->typesByName[$name];
    }

    /**
     * Creates a command
     *
     * @param  string|integer $type
     */
    public function create($type, array $data = [], string $magic = CommandInterface::MAGIC_REQUEST): Command
    {
        return new Command($this->getType($type), $data, $magic);
    }
}
