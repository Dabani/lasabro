<?php

namespace Lasabro\Framework\Console;

use Lasabro\Framework\Plugin\PluginManager;

class PluginActivateCommand extends PluginCommand
{
    protected $signature = 'lasabro:plugin:activate
        {plugin? : Plugin id, for example Lasabro.Hr}
        {--with-dependencies : Also install and switch on the inactive plugins it requires}
        {--all : Switch on every plugin available on this system}';
    protected $description = 'Switch a plugin on (installing it first when needed)';

    public function handle(PluginManager $pm): int
    {
        return $this->guarded($pm, function (PluginManager $pm) {
            if ($this->option('all')) {
                $switched = [];
                foreach ($pm->resolver()->order(array_keys($pm->manifests())) as $id) {
                    $switched = array_merge($switched, $pm->activate($id, true));
                }
            } elseif ($id = $this->argument('plugin')) {
                $switched = $pm->activate((string) $id, (bool) $this->option('with-dependencies'));
            } else {
                $this->error('Give a plugin id or use --all.');

                return self::FAILURE;
            }
            $this->info($switched ? 'Activated: ' . implode(', ', $switched) : 'Nothing to do: already active.');

            return self::SUCCESS;
        });
    }
}
