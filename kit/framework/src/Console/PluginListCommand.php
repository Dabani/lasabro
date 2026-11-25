<?php

namespace Lasabro\Framework\Console;

use Lasabro\Framework\Plugin\PluginManager;

class PluginListCommand extends PluginCommand
{
    protected $signature = 'lasabro:plugin:list';
    protected $description = 'List the plugins available on this system and whether they are installed and active';

    public function handle(PluginManager $pm): int
    {
        return $this->guarded($pm, function (PluginManager $pm) {
            $rows = $pm->status();
            if (! $rows) {
                $this->line('No plugins found. Install one with `composer require lasabro/<name>` or list a folder in config/lasabro.php (plugin_paths).');
            } else {
                $this->table(['Plugin', 'Title', 'Tier', 'Version', 'State', 'Requires'], array_map(fn ($r) => [
                    $r['id'], $r['title'], $r['tier'], $r['version'], $r['state'], implode(', ', array_keys($r['requires'])) ?: '-',
                ], $rows));
            }
            $this->reportSourceErrors();

            return self::SUCCESS;
        });
    }
}
