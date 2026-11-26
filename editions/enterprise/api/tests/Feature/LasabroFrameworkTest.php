<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lasabro\Framework\Contracts\ManifestSource;
use Lasabro\Framework\Plugin\PluginCache;
use Lasabro\Framework\Plugin\PluginManager;
use Tests\TestCase;

/** The kit's plugin engine inside the real application, using the fixture plugin in tests/Fixtures/plugins/demo. */
class LasabroFrameworkTest extends TestCase
{
    use RefreshDatabase;

    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('tests/Fixtures/plugins/demo/src/DemoServiceProvider.php');
        $this->cacheFile = sys_get_temp_dir().'/lasabro-feature-'.bin2hex(random_bytes(4)).'/lasabro.php';
        config(['lasabro.plugin_paths' => [base_path('tests/Fixtures/plugins')], 'lasabro.cache_path' => $this->cacheFile]);
        $this->app->forgetInstance(ManifestSource::class);
        $this->app->forgetInstance(PluginManager::class);
    }

    public function test_the_kit_tables_exist(): void
    {
        foreach (['lasabro_plugins', 'lasabro_plugin_resources', 'lasabro_plugin_events'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }

    public function test_the_fixture_plugin_is_listed_as_not_installed(): void
    {
        $this->artisan('lasabro:plugin:list')->expectsOutputToContain('Lasabro.Demo')->assertSuccessful();
    }

    public function test_activate_deactivate_uninstall_cycle_updates_state_history_and_boot_cache(): void
    {
        $this->artisan('lasabro:plugin:activate', ['plugin' => 'Lasabro.Demo'])->assertSuccessful();
        $this->assertDatabaseHas('lasabro_plugins', ['code' => 'Lasabro.Demo', 'is_active' => 1]);
        $this->assertSame(['Lasabro.Demo'], array_column((new PluginCache)->read($this->cacheFile), 'id'));

        $this->artisan('lasabro:plugin:deactivate', ['plugin' => 'Lasabro.Demo'])->assertSuccessful();
        $this->assertDatabaseHas('lasabro_plugins', ['code' => 'Lasabro.Demo', 'is_active' => 0]);
        $this->assertSame([], (new PluginCache)->read($this->cacheFile));

        $this->artisan('lasabro:plugin:uninstall', ['plugin' => 'Lasabro.Demo'])->assertSuccessful();
        $this->assertDatabaseMissing('lasabro_plugins', ['code' => 'Lasabro.Demo']);
        $this->assertDatabaseHas('lasabro_plugin_events', ['plugin_code' => 'Lasabro.Demo', 'action' => 'activated']);
        $this->assertDatabaseHas('lasabro_plugin_events', ['plugin_code' => 'Lasabro.Demo', 'action' => 'uninstalled']);
    }

    public function test_a_declared_resource_without_a_handler_is_a_warning_not_an_error(): void
    {
        $this->artisan('lasabro:plugin:activate', ['plugin' => 'Lasabro.Demo'])
            ->expectsOutputToContain('no handler for "permissions"')
            ->assertSuccessful();
    }

    public function test_an_unknown_plugin_is_refused_with_a_message(): void
    {
        $this->artisan('lasabro:plugin:activate', ['plugin' => 'Lasabro.Nope'])
            ->expectsOutputToContain('Unknown plugin Lasabro.Nope')
            ->assertFailed();
    }
}
