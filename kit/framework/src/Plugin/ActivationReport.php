<?php

declare(strict_types=1);

namespace Lasabro\Framework\Plugin;

/** Why a plugin can or cannot be switched on. */
final class ActivationReport
{
    public const MISSING = 'missing';       // package not present on this machine
    public const INACTIVE = 'inactive';     // present, but not switched on
    public const VERSION = 'version';       // present, but does not satisfy the required constraint

    /** @param list<array{plugin:string,kind:string,constraint:string,found:?string}> $problems */
    public function __construct(public readonly string $plugin, public readonly array $problems)
    {
    }

    public function ok(): bool
    {
        return $this->problems === [];
    }

    /** @return list<string> */
    public function problemsOfKind(string $kind): array
    {
        return array_values(array_map(
            fn ($p) => $p['plugin'],
            array_filter($this->problems, fn ($p) => $p['kind'] === $kind)
        ));
    }

    public function message(): string
    {
        if ($this->ok()) {
            return "{$this->plugin} can be activated.";
        }
        $parts = [];
        foreach ($this->problems as $p) {
            $parts[] = match ($p['kind']) {
                self::MISSING => "{$p['plugin']} {$p['constraint']} is not installed on this system",
                self::INACTIVE => "{$p['plugin']} is installed but inactive",
                default => "{$p['plugin']} {$p['found']} does not satisfy {$p['constraint']}",
            };
        }

        return "{$this->plugin} cannot be activated: " . implode('; ', $parts) . '.';
    }
}
