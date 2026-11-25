<?php

namespace Lasabro\Framework;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base class of every plugin's service provider. A plugin folder is expected to look like:
 *
 *   src/<Name>ServiceProvider.php      (this class)
 *   routes/api.php                     authenticated, tenant-scoped routes
 *   routes/public.php                  open routes (declare throttling in the file)
 *   database/migrations/               the plugin's tables
 *   resources/lang/<locale>/*.php      texts, merged into the application's normal translation groups
 *   resources/<type>.php               declarations: permissions, menu, widgets, lookups
 *
 * Everything that exists is loaded; nothing has to be declared. Override registerPlugin()/bootPlugin() for the rest.
 */
abstract class PluginServiceProvider extends ServiceProvider
{
    /** @var array<class-string,class-string> model => policy, registered with the Gate */
    protected array $policies = [];

    /** @var list<class-string> console commands the plugin provides */
    protected array $commands = [];

    /** Folder that contains the plugin's composer.json (the parent of src/). */
    protected function pluginPath(string $sub = ''): string
    {
        $root = dirname((string) (new \ReflectionClass(static::class))->getFileName(), 2);

        return $sub === '' ? $root : $root . '/' . ltrim($sub, '/');
    }

    final public function register(): void
    {
        $this->registerPlugin();
    }

    final public function boot(): void
    {
        if (is_dir($dir = $this->pluginPath('database/migrations'))) {
            $this->loadMigrationsFrom($dir);
        }
        $this->loadPluginTranslations();
        $this->loadPluginRoutes();
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
        if ($this->commands && $this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
        $this->bootPlugin();
    }

    /** Bind services and contracts. */
    protected function registerPlugin(): void
    {
    }

    /** Schedule tasks, register observers, listeners and anything that needs the whole application. */
    protected function bootPlugin(): void
    {
    }

    /**
     * Plugin texts join the application's own translation groups (lang/<locale>/hr.php stays __('hr.key')),
     * which keeps every existing translation key unchanged when a module becomes a plugin.
     */
    private function loadPluginTranslations(): void
    {
        $dir = $this->pluginPath('resources/lang');
        if (! is_dir($dir)) {
            return;
        }
        $this->callAfterResolving('translation.loader', function ($loader) use ($dir) {
            if (method_exists($loader, 'addPath')) {
                $loader->addPath($dir);
            }
        });
    }

    private function loadPluginRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }
        $config = $this->app['config'];
        $prefix = (string) $config->get('lasabro.routes.prefix', 'api/v1');
        foreach ([
            'routes/api.php' => (array) $config->get('lasabro.routes.auth_middleware', ['api', 'auth:sanctum', 'org.context']),
            'routes/public.php' => (array) $config->get('lasabro.routes.public_middleware', ['api']),
        ] as $file => $middleware) {
            if (is_file($path = $this->pluginPath($file))) {
                Route::middleware($middleware)->prefix($prefix)->group($path);
            }
        }
    }
}
