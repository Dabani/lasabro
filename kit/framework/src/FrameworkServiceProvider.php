<?php

namespace Lasabro\Framework;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Lasabro\Framework\Console\PluginActivateCommand;
use Lasabro\Framework\Console\PluginDeactivateCommand;
use Lasabro\Framework\Console\PluginDiscoverCommand;
use Lasabro\Framework\Console\PluginDocsCommand;
use Lasabro\Framework\Console\PluginInfoCommand;
use Lasabro\Framework\Console\PluginInstallCommand;
use Lasabro\Framework\Console\PluginListCommand;
use Lasabro\Framework\Console\PluginUninstallCommand;
use Lasabro\Framework\Contracts\ManifestSource;
use Lasabro\Framework\Contracts\MigrationRunner;
use Lasabro\Framework\Contracts\ResourceLedger;
use Lasabro\Framework\Contracts\StateStore;
use Lasabro\Framework\Plugin\DeclarationRegistry;
use Lasabro\Framework\Plugin\PluginCache;
use Lasabro\Framework\Plugin\PluginManager;
use Lasabro\Framework\Support\ArtisanMigrationRunner;
use Lasabro\Framework\Support\ComposerManifestSource;
use Lasabro\Framework\Support\DatabaseResourceLedger;
use Lasabro\Framework\Support\DatabaseStateStore;

/**
 * Entry point of the kit (auto-discovered by Laravel). It wires the plugin engine and then registers the service
 * provider of every ACTIVE plugin, read from the compiled cache so no database query is needed at boot.
 * With no plugins activated the application behaves exactly as it did without the kit.
 */
class FrameworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lasabro.php', 'lasabro');

        $this->app->singleton(DeclarationRegistry::class);
        $this->app->singleton(PluginCache::class);
        $this->app->singleton(ManifestSource::class, fn ($app) => new ComposerManifestSource(
            (string) $app['config']->get('lasabro.package_type', 'lasabro-plugin'),
            (array) $app['config']->get('lasabro.plugin_paths', []),
        ));
        $this->app->singleton(StateStore::class, DatabaseStateStore::class);
        $this->app->singleton(ResourceLedger::class, DatabaseResourceLedger::class);
        $this->app->singleton(MigrationRunner::class, ArtisanMigrationRunner::class);
        $this->app->singleton(PluginManager::class, fn ($app) => new PluginManager(
            $app->make(ManifestSource::class),
            $app->make(StateStore::class),
            $app->make(ResourceLedger::class),
            $app->make(MigrationRunner::class),
            $app->make(DeclarationRegistry::class),
            $app->make(PluginCache::class),
            $this->cacheFile(),
        ));

        $this->registerActivePlugins();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([__DIR__ . '/../config/lasabro.php' => config_path('lasabro.php')], 'lasabro-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PluginListCommand::class, PluginInfoCommand::class, PluginInstallCommand::class, PluginActivateCommand::class,
                PluginDeactivateCommand::class, PluginUninstallCommand::class, PluginDiscoverCommand::class, PluginDocsCommand::class,
            ]);
        }
    }

    private function cacheFile(): string
    {
        return $this->app['config']->get('lasabro.cache_path') ?: $this->app->bootstrapPath('cache/lasabro.php');
    }

    /** Register the providers of the plugins that were active when the cache was last built (dependencies first). */
    private function registerActivePlugins(): void
    {
        foreach ($this->app->make(PluginCache::class)->read($this->cacheFile()) as $entry) {
            if (! class_exists($entry['provider'])) {
                // Package removed without deactivating it: keep the application up and say so.
                Log::warning("LASABRO: plugin {$entry['id']} is listed as active but {$entry['provider']} cannot be loaded. Run `php artisan lasabro:plugin:discover`.");
                continue;
            }
            $this->app->register($entry['provider']);
        }
    }
}
