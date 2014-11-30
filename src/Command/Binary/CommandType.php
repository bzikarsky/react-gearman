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

    /**
     * @var string
     */
    protected $name;

    /**
     * @var integer
     */
    protected $type;

    /**
     * @var string[]
     */
    protected $arguments;

    /**
     * Creates a types
     *
     * @param string   $name
     * @param integer  $type
     * @param string[] $arguments
     */
    public function __construct($name, $type, array $arguments = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->arguments = $arguments;
    }

    /**
     * Returns a user-readable string representation of the type
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name . '(' . $this->type . ')';
    }

    /**
     * Returns an ordered list of all the command's arguments
     *
     * @return string[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Return the type's name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the type's integer code
     *
     * @return integer
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Checks whether a certain argument is exists in this type
     *
     * @param  string $name
     * @return bool
     */
    public function hasArgument($name)
    {
        return in_array($name, $this->arguments);
    }
}
