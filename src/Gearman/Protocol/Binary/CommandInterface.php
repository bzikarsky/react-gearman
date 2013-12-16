<?php

namespace Gearman\Protocol\Binary;

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
     *
     * @const string
     */
    const MAGIC_REQUEST     = "\0REQ";

    /**
     * Magic bytes for a RESPONSE command
     *
     * @const string
     */
    const MAGIC_RESPONSE    = "\0RES";

    /**
     * Argument identifier for opaque command data
     *
     * @const string
     */
    const DATA = '*';

    /**
     * Format definition for a command's packet-header in php.net/unpack syntax
     *
     * @const string
     */
    const HEADER_READ_FORMAT = 'a4magic/Ntype/Nsize';

    /**
     * Format definition for a command's packet-header in php.net/pack syntax
     *
     * @const string
     */
    const HEADER_WRITE_FORMAT = 'a4NN';

    /**
     * Byte-length of a packet-header
     *
     * @const integer
     */
    const HEADER_LENGTH = 12;

    /**
     * Delimiter for a command's arguments
     *
     * @const string
     */
    const ARGUMENT_DELIMITER = "\0";

    /**
     * Returns the command's argument-value
     * If the argument is not set yet, $default is returned
     *
     * @param string $key
     * @param null $default
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function get($key, $default = null);

    /**
     * Set the command's argument to given value
     *
     * @param string $key
     * @param mixed $value
     * @throws InvalidArgumentException
     */
    public function set($key, $value);

    /**
     * Returns all arguments as key => value pairs in defined order
     * If one argument has no value yet, $default is returned
     *
     * @param null $default
     * @return array
     */
    public function getAll($default = null);

    /**
     * Returns the command's name
     *
     * @return string
     */
    public function getName();

    /**
     * Returns the command's type
     *
     * @return integer
     */
    public function getType();

    /**
     * Returns the command's magic bytes
     *
     * @return string
     */
    public function getMagic();

}
