<?php

namespace Lasabro\Framework\Console;

use Lasabro\Framework\Plugin\PluginManager;

class PluginUninstallCommand extends PluginCommand
{
    protected $signature = 'lasabro:plugin:uninstall
        {plugin : Plugin id}
        {--purge : Also delete the plugin\'s tables and data (cannot be undone)}
        {--force : Do not ask for confirmation}';
    protected $description = 'Forget an inactive, unused plugin. Data are deleted only with --purge';

    public function handle(PluginManager $pm): int
    {
        return $this->guarded($pm, function (PluginManager $pm) {
            $id = (string) $this->argument('plugin');
            $purge = (bool) $this->option('purge');
            $pm->manifest($id);
            if ($purge && ! $this->option('force')
                && ! $this->confirm("This deletes every table and row owned by {$id}. It cannot be undone. Continue?", false)) {
                $this->line('Cancelled.');

                return self::SUCCESS;
            }
            $pm->uninstall($id, $purge);
            $this->info("{$id} uninstalled" . ($purge ? ' and its data deleted.' : ' (data kept). Run `composer remove` to remove the package.'));

            return self::SUCCESS;
        });
    }
}
