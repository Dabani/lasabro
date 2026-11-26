<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lasabro\Framework\Contracts\ManifestSource;
use Lasabro\Framework\Plugin\PluginManager;
use Tests\TestCase;

/** Lasabro.Procurement as a real plugin: discovered, activated on existing data (--adopt), hidden and shown again. */
class LasabroProcurementPluginTest extends TestCase
{
    use CopsTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lasabro.cache_path' => sys_get_temp_dir().'/lasabro-proc-'.bin2hex(random_bytes(4)).'/lasabro.php']);
        $this->app->forgetInstance(ManifestSource::class);
        $this->app->forgetInstance(PluginManager::class);
        $this->bootCops();   // DatabaseSeeder seeds the procurement lookups, permissions and menu, as before the extraction
    }

    private function activeMenuCount(): int
    {
        return DB::table('menu_items')->where('code', 'like', 'procurement%')->where('is_active', true)->count();
    }

    public function test_the_plugin_is_discovered_and_its_requirements_are_provided_by_the_host(): void
    {
        $this->artisan('lasabro:plugin:list')
            ->expectsOutputToContain('Lasabro.Procurement')
            ->expectsOutputToContain('provided')
            ->assertSuccessful();
    }

    public function test_activating_on_existing_data_adopts_without_reseeding_and_deactivating_hides_the_menu(): void
    {
        $this->assertGreaterThan(0, $this->activeMenuCount());
        $permissions = DB::table('permissions')->where('code', 'like', 'procurement.%')->count();

        $this->artisan('lasabro:plugin:activate', ['plugin' => 'Lasabro.Procurement', '--adopt' => true])->assertSuccessful();
        $this->assertDatabaseHas('lasabro_plugins', ['code' => 'Lasabro.Procurement', 'is_active' => 1]);
        $this->assertSame($permissions, DB::table('permissions')->where('code', 'like', 'procurement.%')->count());
        $this->assertGreaterThan(0, DB::table('lasabro_plugin_resources')->where('plugin_code', 'Lasabro.Procurement')->where('type', 'seeders')->count());

        $this->artisan('lasabro:plugin:deactivate', ['plugin' => 'Lasabro.Procurement'])->assertSuccessful();
        $this->assertSame(0, $this->activeMenuCount());
        $this->assertSame($permissions, DB::table('permissions')->where('code', 'like', 'procurement.%')->count());   // nothing deleted

        $this->artisan('lasabro:plugin:activate', ['plugin' => 'Lasabro.Procurement', '--adopt' => true])->assertSuccessful();
        $this->assertGreaterThan(0, $this->activeMenuCount());
    }
}
