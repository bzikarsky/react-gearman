<?php

namespace Zikarsky\React\Gearman\Command\Binary;

/**
 * A command-factory creates command instances from given data
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
interface CommandFactoryInterface
{
    /**
     * Creates a Command with given type (name or inetger code representation). $data can be used to initialize
     * a Commands arguments.
     *
     * @param  string|integer   $type
     * @param  array            $data
     * @param  string           $magic
     * @return CommandInterface
     */
    public function create($type, array $data = [], $magic = CommandInterface::MAGIC_REQUEST);
}
