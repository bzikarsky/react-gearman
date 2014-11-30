<?php

namespace Gearman\Protocol\Binary;

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
    protected $typesByName = [];

    /**
     * @var CommandType[]
     */
    protected $typesByCode = [];

    /**
     * @param CommandType $type
     */
    public function addType(CommandType $type)
    {
        $this->typesByName[$type->getName()] = $type;
        $this->typesByCode[$type->getType()] = $type;
    }

    /**
     * @param $type
     * @return CommandType
     * @throws InvalidArgumentException
     */
    public function getType($type)
    {
        return is_string($type)
            ? $this->getTypeByName($type)
            : $this->getTypeByCode($type)
        ;
    }

    /**
     * @param  integer                  $code
     * @return CommandType
     * @throws InvalidArgumentException
     */
    public function getTypeByCode($code)
    {
        if (!is_int($code) || !isset($this->typesByCode[$code])) {
            throw new InvalidArgumentException(__METHOD__  . ' requires $code to be a CommandType identifying integer code');
        }

        return $this->typesByCode[$code];
    }

    /**
     * @param  string      $name
     * @return CommandType
     *
     * @throws InvalidArgumentException
     */
    public function getTypeByName($name)
    {
        if (!is_string($name) || !isset($this->typesByName[$name])) {
            throw new InvalidArgumentException(__METHOD__  . ' requires $name to be a CommandType identifying name-string');
        }

        return $this->typesByName[$name];
    }

    /**
     * Creates a command
     *
     * @param  string|integer $type
     * @param  array          $data
     * @param  string         $magic
     * @return Command
     */
    public function create($type, array $data = [], $magic = CommandInterface::MAGIC_REQUEST)
    {
        return new Command($this->getType($type), $data, $magic);
    }

}
