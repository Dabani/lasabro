<?php

namespace Lasabro\Framework\Console;

use Lasabro\Framework\Plugin\PluginManager;

class PluginDiscoverCommand extends PluginCommand
{
    protected $signature = 'lasabro:plugin:discover';
    protected $description = 'Rebuild the boot cache of active plugins (run after composer update or deploy)';

    public function handle(PluginManager $pm): int
    {
        return $this->guarded($pm, function (PluginManager $pm) {
            $pm->rebuildCache();
            $this->info('Plugin boot cache rebuilt.');
            $this->reportSourceErrors();

            return self::SUCCESS;
        });
    }
}
