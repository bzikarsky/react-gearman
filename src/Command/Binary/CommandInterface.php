<?php

namespace Zikarsky\React\Gearman\Command\Binary;

use InvalidArgumentException;

/**
 * A Command is the representation of a Gearman binary command packet as defined in
 * @see http://gearman.org/protocol/
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
interface CommandInterface
{

    /**
     * Magic bytes for a REQUEST command
     */
    public const MAGIC_REQUEST     = "\0REQ";

    /**
     * Magic bytes for a RESPONSE command
     */
    public const MAGIC_RESPONSE    = "\0RES";

    /**
     * Argument identifier for opaque command data
     */
    public const DATA = '*';

    /**
     * Format definition for a command's packet-header in php.net/unpack syntax
     */
    public const HEADER_READ_FORMAT = 'a4magic/Ntype/Nsize';

    /**
     * Format definition for a command's packet-header in php.net/pack syntax
     */
    public const HEADER_WRITE_FORMAT = 'a4NN';

    /**
     * Byte-length of a packet-header
     */
    public const HEADER_LENGTH = 12;

    /**
     * Delimiter for a command's arguments
     */
    public const ARGUMENT_DELIMITER = "\0";

    /**
     * Returns the command's argument-value
     * If the argument is not set yet, $default is returned
     * @throws InvalidArgumentException
     */
    public function get(string $key, $default = null);

    /**
     * Set the command's argument to given value
     * @throws InvalidArgumentException
     */
    public function set(string $key, $value);

    /**
     * Returns all arguments as key => value pairs in defined order
     * If one argument has no value yet, $default is returned
     */
    public function getAll($default = null): array;

    /**
     * Returns the command's name
     */
    public function getName(): string;

    /**
     * Returns the command's type
     */
    public function getType(): int;

    /**
     * Returns the command's magic bytes
     */
    public function getMagic(): string;

    /**
     * Returns whether the job is one of the given command types
     */
    public function is(string ...$type): bool;
}
