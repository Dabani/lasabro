<?php

declare(strict_types=1);

namespace Lasabro\Framework\Contracts;

use Lasabro\Framework\Plugin\PluginState;

/** Persistent plugin state and history. The database implementation uses the lasabro_plugins and lasabro_plugin_events tables. */
interface StateStore
{
    /** @return array<string,PluginState> keyed by plugin id */
    public function all(): array;

    public function save(PluginState $state): void;

    public function forget(string $pluginId): void;

    /** Append to the plugin history (installed, activated, deactivated, uninstalled...). */
    public function event(string $pluginId, string $action, ?string $detail = null): void;
}
