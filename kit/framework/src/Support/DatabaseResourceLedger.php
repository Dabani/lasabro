<?php

namespace Lasabro\Framework\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lasabro\Framework\Contracts\ResourceLedger;

final class DatabaseResourceLedger implements ResourceLedger
{
    public function record(string $pluginId, string $type, array $codes): void
    {
        DB::transaction(function () use ($pluginId, $type, $codes) {
            $q = DB::table('lasabro_plugin_resources')->where('plugin_code', $pluginId)->where('type', $type);
            $existing = $q->pluck('code')->all();
            $now = now();
            $rows = [];
            foreach (array_diff($codes, $existing) as $code) {
                $rows[] = ['id' => (string) Str::ulid(), 'plugin_code' => $pluginId, 'type' => $type, 'code' => $code, 'created_at' => $now, 'updated_at' => $now];
            }
            if ($rows) {
                DB::table('lasabro_plugin_resources')->insert($rows);
            }
            // Codes the plugin no longer declares are forgotten (the handler decides what happens to their rows).
            $stale = array_diff($existing, $codes);
            if ($stale) {
                DB::table('lasabro_plugin_resources')->where('plugin_code', $pluginId)->where('type', $type)->whereIn('code', $stale)->delete();
            }
        });
    }

    public function codes(string $pluginId, string $type): array
    {
        return DB::table('lasabro_plugin_resources')->where('plugin_code', $pluginId)->where('type', $type)->orderBy('code')->pluck('code')->all();
    }

    public function clear(string $pluginId): void
    {
        DB::table('lasabro_plugin_resources')->where('plugin_code', $pluginId)->delete();
    }
}
