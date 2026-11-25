<?php

namespace Lasabro\Framework\Console;

use Lasabro\Framework\Plugin\PluginManager;

class PluginInfoCommand extends PluginCommand
{
    protected $signature = 'lasabro:plugin:info {plugin : Plugin id, for example Lasabro.Hr}';
    protected $description = 'Show a plugin\'s details and its dependency notice';

    public function handle(PluginManager $pm): int
    {
        return $this->guarded($pm, function (PluginManager $pm) {
            $m = $pm->manifest((string) $this->argument('plugin'));
            $state = collect($pm->status())->firstWhere('id', $m->id)['state'] ?? 'not installed';
            $this->line("<options=bold>{$m->id}</>  {$m->title}  ({$m->version}, {$state})");
            $this->line("Package:   {$m->package}");
            $this->line("Tier:      {$m->tier}");
            $this->line("Path:      {$m->path}");
            $this->line('Frontends: ' . ($m->frontends ? implode(', ', array_map(fn ($k, $v) => "{$k} => {$v}", array_keys($m->frontends), $m->frontends)) : '-'));
            $this->line('Declares:  ' . ($m->declares ? implode(', ', $m->declares) : '-'));
            $this->newLine();
            $this->line($pm->resolver()->notice($m->id));

            return self::SUCCESS;
        });
    }
}
