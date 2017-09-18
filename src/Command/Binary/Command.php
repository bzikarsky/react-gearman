<?php

namespace Zikarsky\React\Gearman\Command\Binary;

use InvalidArgumentException;

class Command implements CommandInterface
{
    /**
     * @var CommandType
     */
    protected $type;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $magic;

    public function __construct(CommandType $type, array $data, $magic)
    {
        $this->type = $type;
        $this->magic = $magic;

        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function __toString()
    {
        $values = array_map(function ($key) {
            return $key . "=" . $this->get($key, "NULL", true);
        }, $this->type->getArguments());

        return $this->type->__toString() . '[' . implode('&', $values) . ']';
    }

    public function set($key, $value)
    {
        if (!$this->type->hasArgument($key)) {
            throw new InvalidArgumentException($this->type->getName() . "  does not have a $key argument");
        }

        $this->data[$key] = $value;
    }

    public function get($key, $default = null)
    {
        if (!$this->type->hasArgument($key)) {
            throw new InvalidArgumentException($this->type->getName() . "  does not have a $key argument");
        }

        $data = isset($this->data[$key]) ? $this->data[$key] : $default;

        return $data;
    }

    public function getAll($default = null)
    {
        $args = [];
        foreach ($this->type->getArguments() as $arg) {
            $args[$arg] = $this->get($arg, $default);
        }

        return $args;
    }

    public function getType()
    {
        return $this->type->getType();
    }

    public function getName()
    {
        return $this->type->getName();
    }

    public function getMagic()
    {
        return $this->magic;
    }
}
