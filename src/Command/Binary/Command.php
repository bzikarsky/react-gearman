<?php

namespace Zikarsky\React\Gearman\Command\Binary;

use InvalidArgumentException;

class Command implements CommandInterface
{
    private CommandType $type;
    private array $data = [];
    private string $magic;

    public function __construct(CommandType $type, array $data, string $magic)
    {
        $this->type = $type;
        $this->magic = $magic;

        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function __toString(): string
    {
        $values = array_map(function ($key) {
            return $key . "=" . $this->get($key, "NULL");
        }, $this->type->getArguments());

        return $this->type->__toString() . '[' . implode('&', $values) . ']';
    }

    public function set($key, $value): void
    {
        if (!$this->type->hasArgument($key)) {
            throw new InvalidArgumentException($this->type->getName() . "  does not have a $key argument");
        }

        $this->data[$key] = $value;
    }

    public function get(string $key, $default = null)
    {
        if (!$this->type->hasArgument($key)) {
            throw new InvalidArgumentException($this->type->getName() . "  does not have a $key argument");
        }

        $data = $this->data[$key] ?? $default;

        return $data;
    }

    public function getAll($default = null): array
    {
        $args = [];
        foreach ($this->type->getArguments() as $arg) {
            $args[$arg] = $this->get($arg, $default);
        }

        return $args;
    }

    public function getType(): int
    {
        return $this->type->getType();
    }

    public function getName(): string
    {
        return $this->type->getName();
    }

    public function getMagic(): string
    {
        return $this->magic;
    }

    public function is(string ...$types): bool
    {
        return in_array($this->getName(), $types);
    }
}
