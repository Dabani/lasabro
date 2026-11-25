<?php

namespace Lasabro\Framework\Console;

use Lasabro\Framework\Plugin\PluginManager;

class PluginInstallCommand extends PluginCommand
{
    protected $signature = 'lasabro:plugin:install {plugin : Plugin id}';
    protected $description = 'Run a plugin\'s migrations (and those of the plugins it requires) without switching it on';

    public function handle(PluginManager $pm): int
    {
        return $this->guarded($pm, function (PluginManager $pm) {
            $done = $pm->install((string) $this->argument('plugin'));
            $this->info($done ? 'Installed: ' . implode(', ', $done) : 'Already installed.');

            return self::SUCCESS;
        });
    }
}
