<?php

namespace Lasabro\Framework\Console;

use Lasabro\Framework\Plugin\PluginManager;

class PluginDeactivateCommand extends PluginCommand
{
    protected $signature = 'lasabro:plugin:deactivate
        {plugin : Plugin id}
        {--cascade : Also switch off the active plugins that require it}';
    protected $description = 'Switch a plugin off. Its data are kept';

    public function handle(PluginManager $pm): int
    {
        return $this->guarded($pm, function (PluginManager $pm) {
            $id = (string) $this->argument('plugin');
            $manifest = $pm->manifest($id);
            $off = $pm->deactivate($id, (bool) $this->option('cascade'));
            $this->info($off ? 'Deactivated: ' . implode(', ', $off) . ' (data kept).' : "{$manifest->id} was not active.");

            return self::SUCCESS;
        });
    }
}
