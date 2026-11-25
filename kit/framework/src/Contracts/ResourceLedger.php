<?php

declare(strict_types=1);

namespace Lasabro\Framework\Contracts;

/**
 * Remembers which permission, menu, widget... codes each plugin created, so deactivating or removing a plugin touches
 * exactly its own rows and never anything else. Kept in its own table: the application's tables are not altered.
 */
interface ResourceLedger
{
    /** @param list<string> $codes */
    public function record(string $pluginId, string $type, array $codes): void;

    /** @return list<string> */
    public function codes(string $pluginId, string $type): array;

    public function clear(string $pluginId): void;
}
