<?php

namespace Zikarsky\React\Gearman\Command\Binary;

use InvalidArgumentException;
use OutOfBoundsException;
use Countable;
use Zikarsky\React\Gearman\Command\Exception as ProtocolException;

/**
 * The Buffer converts byte-strings in a FIFO way to commands
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
class ReadBuffer implements Countable
{
    /**
     * @var string
     */
    protected $buffer;

    /**
     * @var CommandInterface[]
     */
    protected $commandBuffer;

    /**
     * @var CommandInterface|null
     */
    protected $currentCommand;

    /**
     * @var integer
     */
    protected $requiredBytes;

    /**
     * @var CommandFactoryInterface
     */
    protected $commandFactory = null;

    /**
     * Creates the protocol buffer
     *
     * @param CommandFactoryInterface $commandFactory
     */
    public function __construct(CommandFactoryInterface $commandFactory)
    {
        $this->commandFactory = $commandFactory;
        $this->init();
    }

    /**
     * Init protocol buffer
     */
    protected function init()
    {
        // byte buffer is empty
        $this->buffer = "";

        // no commands have been read yet
        $this->commandBuffer = [];

        // there is no command currently being read
        $this->currentCommand = null;

        // we expect to read a command header next
        $this->requiredBytes = CommandInterface::HEADER_LENGTH;
    }

    /**
     * Push bytes into the buffer, returns whether full commands were read
     *
     * @param  string                   $bytes
     * @return bool
     * @throws InvalidArgumentException
     * @throws ProtocolException
     */
    public function push($bytes)
    {
        if (!is_string($bytes)) {
            throw new InvalidArgumentException("Only a raw byte string can be pushed into the buffer");
        }

        $this->buffer .= $bytes;

        // if our buffer matches or exceeds the current required byte count
        // extract the bytes and handle them
        while (strlen($this->buffer) >= $this->requiredBytes) {
            $data = substr($this->buffer, 0, $this->requiredBytes);
            $this->buffer = substr($this->buffer, $this->requiredBytes);
            $this->handleBuffer($data);
        }

        // return whether there are commands waiting to be handled
        return count($this->commandBuffer);
    }

    /**
     * Returns the next command in a FIFO way
     *
     * @return CommandInterface
     * @throws OutOfBoundsException
     */
    public function shift()
    {
        if (!count($this->commandBuffer)) {
            throw new OutOfBoundsException("Command buffer is empty");
        }

        return array_shift($this->commandBuffer);
    }

    /**
     * Handles given buffer with respect to current buffer state
     *
     * @param $buffer
     * @throws InvalidArgumentException
     * @throws ProtocolException
     */
    protected function handleBuffer($buffer)
    {
        $len = strlen($buffer);

         // @codeCoverageIgnoreStart
         // This is an internal safeguard which is not testable
        if ($len != $this->requiredBytes) {
            throw new InvalidArgumentException("Expected a string with length of $this->requiredBytes. Got $len bytes");
        }
        // @codeCoverageIgnoreEnd

        if (!$this->currentCommand) {
            $this->handleHeader($buffer);
        } else {
            $this->handleBody($buffer);
        }
    }

    /**
     * Handles a package header
     *
     * @param string $buffer
     */
    protected function handleHeader($buffer)
    {
        $result = unpack(CommandInterface::HEADER_READ_FORMAT, $buffer);
        list($magic, $type, $size) = array_values($result);

        // set state: set command being read and its body size as required buffer-length to proceed
        $this->currentCommand = $this->commandFactory->create($type, [], $magic);
        $this->requiredBytes = $size;
    }

    /**
     * Handles a package body
     *
     * @param  string            $buffer
     * @throws ProtocolException
     */
    protected function handleBody($buffer)
    {
        $i = 0;
        // split buffer into arguments
        $args = array_keys($this->currentCommand->getAll());

        foreach ($args as $arg) {
            if (empty($buffer)) {
                throw new ProtocolException("Invalid package-header: header.size bytes did not contain full package body");
            }
            $nextArgs = '';
            while ($i < strlen($buffer)) {
                $char = substr($buffer, $i, 1);
                $i++;
                if ($char == CommandInterface::ARGUMENT_DELIMITER) {
                    break;
                }
                $nextArgs .= $char;
            }

            $this->currentCommand->set($arg, $nextArgs);
        }

        // set set state: put complete command into buffer, and expect a header next
        array_push($this->commandBuffer, $this->currentCommand);
        $this->currentCommand   = null;
        $this->requiredBytes    = CommandInterface::HEADER_LENGTH;
    }


    /**
     * Return count of parsed and waiting commands
     *
     * @return integer
     */
    public function count()
    {
        return count($this->commandBuffer);
    }
}
