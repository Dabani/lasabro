<?php

namespace Lasabro\Framework\Support;

use Illuminate\Support\Facades\Artisan;
use Lasabro\Framework\Contracts\MigrationRunner;
use Lasabro\Framework\Plugin\Manifest;
use Lasabro\Framework\Plugin\PluginException;

/** Runs a plugin's own database/migrations folder with the normal migrator, so the `migrations` table stays the single record. */
final class ArtisanMigrationRunner implements MigrationRunner
{
    public function run(Manifest $plugin): void
    {
        if ($dir = $this->directory($plugin)) {
            $this->call('migrate', $dir);
        }
    }

    public function rollback(Manifest $plugin): void
    {
        if ($dir = $this->directory($plugin)) {
            $this->call('migrate:reset', $dir);
        }
    }

    private function directory(Manifest $plugin): ?string
    {
        $dir = $plugin->path . '/database/migrations';

        return is_dir($dir) ? $dir : null;
    }

    private function call(string $command, string $dir): void
    {
        $code = Artisan::call($command, ['--path' => $dir, '--realpath' => true, '--force' => true]);
        if ($code !== 0) {
            throw new PluginException("`{$command}` failed for {$dir}:\n" . trim(Artisan::output()));
        }
    }
}
