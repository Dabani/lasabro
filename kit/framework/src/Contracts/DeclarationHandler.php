<?php

declare(strict_types=1);

namespace Lasabro\Framework\Contracts;

use Lasabro\Framework\Plugin\Manifest;

/**
 * Turns one kind of plugin declaration (permissions, menu, widgets, lookups...) into application data.
 * The plugin that owns the underlying tables (Lasabro.Core) registers a handler for each type.
 * Handlers must be idempotent: sync() is called on every activation and updates by code.
 */
interface DeclarationHandler
{
    /** The declaration type this handler understands, matching resources/<type>.php. */
    public function type(): string;

    /**
     * @param list<array<string,mixed>> $items rows returned by the plugin's resources/<type>.php
     * @return list<string> the codes now owned by the plugin
     */
    public function sync(Manifest $plugin, array $items): array;

    /** Show or hide the rows owned by the plugin without deleting them. @param list<string> $codes */
    public function setActive(Manifest $plugin, bool $active, array $codes): void;

    /** Delete the rows owned by the plugin (uninstall). @param list<string> $codes */
    public function remove(Manifest $plugin, array $codes): void;
}
