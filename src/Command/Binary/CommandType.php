<?php

namespace Zikarsky\React\Gearman\Command\Binary;

/**
 * A command types represent the specification for a Gearman binary command packet
 * as defined in @see http://gearman.org/protocol/
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
class CommandType
{
    protected string $name;
    protected int $type;

    /** @var string[] */
    protected array $arguments;

    /**
     * Creates a type
     *
     * @param string[] $arguments
     */
    public function __construct(string $name, int $type, array $arguments = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->arguments = $arguments;
    }

    /**
     * Returns a user-readable string representation of the type
     */
    public function __toString(): string
    {
        return $this->name . '(' . $this->type . ')';
    }

    /**
     * Returns an ordered list of all the command's arguments
     *
     * @return string[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Return the type's name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the type's integer code
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Checks whether a certain argument is exists in this type
     */
    public function hasArgument(string $name): bool
    {
        return in_array($name, $this->arguments);
    }
}
