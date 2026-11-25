<?php

declare(strict_types=1);

namespace Lasabro\Framework\Plugin;

/** What this installation knows about a plugin: installed (migrations ran) and whether it is switched on. */
final class PluginState
{
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly bool $active,
        public readonly ?string $installedAt = null,
        public readonly ?string $activatedAt = null,
    ) {
    }

    public function withActive(bool $active, ?string $at = null): self
    {
        return new self($this->id, $this->version, $active, $this->installedAt, $active ? ($at ?? $this->activatedAt) : $this->activatedAt);
    }
}
