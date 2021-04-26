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
    /** @var CommandInterface[] */
    private array $commandBuffer = [];
    private string $buffer = "";
    private ?CommandInterface $currentCommand = null;
    private int $requiredBytes = CommandInterface::HEADER_LENGTH;
    private CommandFactoryInterface $commandFactory;

    public function __construct(CommandFactoryInterface $commandFactory)
    {
        $this->commandFactory = $commandFactory;
    }

    /**
     * Push bytes into the buffer, returns whether full commands were read
     *
     * @throws InvalidArgumentException
     * @throws ProtocolException
     */
    public function push(string $bytes): bool
    {
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
    public function shift(): CommandInterface
    {
        if (!count($this->commandBuffer)) {
            throw new OutOfBoundsException("Command buffer is empty");
        }

        return array_shift($this->commandBuffer);
    }

    /**
     * Handles given buffer with respect to current buffer state
     *
     * @throws InvalidArgumentException
     * @throws ProtocolException
     */
    private function handleBuffer(string $buffer): void
    {
        // @codeCoverageIgnoreStart
        // This is an internal safeguard which is not testable
        if (strlen($buffer) !== $this->requiredBytes) {
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
     */
    private function handleHeader(string $buffer): void
    {
        $result = unpack(CommandInterface::HEADER_READ_FORMAT, $buffer);
        [$magic, $type, $size] = array_values($result);

        // set state: set command being read and its body size as required buffer-length to proceed
        $this->currentCommand = $this->commandFactory->create($type, [], $magic);
        $this->requiredBytes = $size;
    }

    /**
     * Handles a package body
     * @throws ProtocolException
     */
    private function handleBody(string $buffer): void
    {
        // split buffer into arguments
        $args = array_keys($this->currentCommand->getAll());
        $argv = $buffer !== '' ? explode(CommandInterface::ARGUMENT_DELIMITER, $buffer, count($args)) : [];

        // validate argument-count vs expected argument count
        if (count($args) !== count($argv)) {
            throw new ProtocolException("Invalid package-header: header.size bytes did not contain full package body");
        }

        // save arguments
        foreach ($args as $arg) {
            $this->currentCommand->set($arg, array_shift($argv));
        }

        // set set state: put complete command into buffer, and expect a header next
        $this->commandBuffer[]  = $this->currentCommand;
        $this->currentCommand   = null;
        $this->requiredBytes    = CommandInterface::HEADER_LENGTH;
    }

    /**
     * Return count of parsed and waiting commands
     */
    public function count(): int
    {
        return count($this->commandBuffer);
    }
}
