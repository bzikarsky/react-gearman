<?php

namespace Zikarsky\React\Gearman;

use Evenement\EventEmitter;

class UnknownTask extends EventEmitter implements TaskInterface
{

    protected string $handle;

    public function __construct(string $handle)
    {
        $this->handle = $handle;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getFunction(): string
    {
        return '';
    }

    public function getWorkload(): ?string
    {
        return null;
    }

    public function getPriority(): string
    {
        return '';
    }

    public function getUniqueId(): string
    {
        return '';
    }
}
