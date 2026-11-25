<?php

declare(strict_types=1);

namespace Lasabro\Framework\Plugin;

use Lasabro\Framework\Contracts\DeclarationHandler;

final class DeclarationRegistry
{
    /** @var array<string,DeclarationHandler> */
    private array $handlers = [];

    public function register(DeclarationHandler $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    public function get(string $type): ?DeclarationHandler
    {
        return $this->handlers[$type] ?? null;
    }
}
