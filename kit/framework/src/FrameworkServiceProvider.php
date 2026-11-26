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
use Lasabro\Framework\Plugin\DependencyResolver;
use Lasabro\Framework\Plugin\PluginCache;
use Lasabro\Framework\Plugin\PluginManager;
use Lasabro\Framework\Support\ArtisanMigrationRunner;
use Lasabro\Framework\Support\ComposerManifestSource;
use Lasabro\Framework\Support\DatabaseResourceLedger;
use Lasabro\Framework\Support\DatabaseStateStore;
use Lasabro\Framework\Support\SeederDeclarationHandler;

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
            (array) $app['config']->get('lasabro.provided', []),
        ));
        $this->app->singleton(SeederDeclarationHandler::class, fn ($app) => new SeederDeclarationHandler($app->make(ResourceLedger::class)));
        $this->app->afterResolving(DeclarationRegistry::class, fn (DeclarationRegistry $r, $app) => $r->register($app->make(SeederDeclarationHandler::class)));

        $this->registerActivePlugins();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadPluginMigrations();
        $this->publishes([__DIR__ . '/../config/lasabro.php' => config_path('lasabro.php')], 'lasabro-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PluginListCommand::class, PluginInfoCommand::class, PluginInstallCommand::class, PluginActivateCommand::class,
                PluginDeactivateCommand::class, PluginUninstallCommand::class, PluginDiscoverCommand::class, PluginDocsCommand::class,
            ]);
        }
    }

    /**
     * `php artisan migrate` creates the tables of every plugin package that is installed, active or not: installing the package is
     * what brings its schema; activation only switches the plugin's behaviour on. (Console only: web requests never migrate.)
     */
    private function loadPluginMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }
        foreach ($this->app->make(ManifestSource::class)->manifests() as $manifest) {
            if (is_dir($dir = $manifest->path . '/database/migrations')) {
                $this->loadMigrationsFrom($dir);
            }
        }
    }

    /**
     * The plugins whose providers to register: the compiled cache of active plugins, or, when `lasabro.boot_all` is on
     * (tests and local development), every installed plugin in dependency order regardless of activation state.
     *
     * @return list<array{id:string,provider:string,path:string}>
     */
    private function pluginsToBoot(): array
    {
        if (! $this->app['config']->get('lasabro.boot_all', false)) {
            return $this->app->make(PluginCache::class)->read($this->cacheFile());
        }
        $manifests = $this->app->make(ManifestSource::class)->manifests();
        $entries = [];
        foreach ((new DependencyResolver($manifests, []))->order(array_keys($manifests)) as $id) {
            $entries[] = ['id' => $id, 'provider' => $manifests[$id]->provider, 'path' => $manifests[$id]->path];
        }

        return $entries;
    }

    private function cacheFile(): string
    {
        return $this->app['config']->get('lasabro.cache_path') ?: $this->app->bootstrapPath('cache/lasabro.php');
    }

    /** Register the providers of the plugins that were active when the cache was last built (dependencies first). */
    private function registerActivePlugins(): void
    {
        foreach ($this->pluginsToBoot() as $entry) {
            if (! class_exists($entry['provider'])) {
                // Package removed without deactivating it: keep the application up and say so.
                Log::warning("LASABRO: plugin {$entry['id']} is listed as active but {$entry['provider']} cannot be loaded. Run `php artisan lasabro:plugin:discover`.");
                continue;
            }
            $this->app->register($entry['provider']);
        }
    }
}
