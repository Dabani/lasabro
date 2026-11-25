<?php

namespace Lasabro\Framework\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lasabro\Framework\Contracts\StateStore;
use Lasabro\Framework\Plugin\PluginState;

final class DatabaseStateStore implements StateStore
{
    public function all(): array
    {
        if (! Schema::hasTable('lasabro_plugins')) {
            return [];   // before the kit's own migration has run there is simply nothing installed
        }
        $out = [];
        foreach (DB::table('lasabro_plugins')->orderBy('code')->get() as $r) {
            $out[$r->code] = new PluginState($r->code, $r->version, (bool) $r->is_active, $r->installed_at, $r->activated_at);
        }

        return $out;
    }

    public function save(PluginState $state): void
    {
        $now = now();
        $values = [
            'version' => $state->version,
            'is_active' => $state->active,
            'installed_at' => $state->installedAt,
            'activated_at' => $state->activatedAt,
            'updated_at' => $now,
        ];
        if (DB::table('lasabro_plugins')->where('code', $state->id)->exists()) {
            DB::table('lasabro_plugins')->where('code', $state->id)->update($values);
        } else {
            DB::table('lasabro_plugins')->insert($values + ['id' => (string) Str::ulid(), 'code' => $state->id, 'created_at' => $now]);
        }
    }

    public function forget(string $pluginId): void
    {
        DB::table('lasabro_plugins')->where('code', $pluginId)->delete();
    }

    public function event(string $pluginId, string $action, ?string $detail = null): void
    {
        DB::table('lasabro_plugin_events')->insert([
            'id' => (string) Str::ulid(), 'plugin_code' => $pluginId, 'action' => $action, 'detail' => $detail, 'created_at' => now(),
        ]);
    }
}
