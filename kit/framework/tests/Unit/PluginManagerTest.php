<?php

declare(strict_types=1);

namespace Lasabro\Framework\Tests\Unit;

use Lasabro\Framework\Plugin\DeclarationRegistry;
use Lasabro\Framework\Plugin\PluginCache;
use Lasabro\Framework\Plugin\PluginException;
use Lasabro\Framework\Plugin\PluginManager;
use Lasabro\Framework\Tests\Support\ArrayManifestSource;
use Lasabro\Framework\Tests\Support\ArrayResourceLedger;
use Lasabro\Framework\Tests\Support\ArrayStateStore;
use Lasabro\Framework\Tests\Support\Fixtures;
use Lasabro\Framework\Tests\Support\RecordingHandler;
use Lasabro\Framework\Tests\Support\RecordingMigrationRunner;
use PHPUnit\Framework\TestCase;

class PluginManagerTest extends TestCase
{
    private ArrayStateStore $states;
    private ArrayResourceLedger $ledger;
    private RecordingMigrationRunner $migrations;
    private RecordingHandler $permissions;
    private string $cacheFile;
    private PluginManager $pm;

    protected function setUp(): void
    {
        $coreDir = Fixtures::tempPlugin('permissions', [['code' => 'users.view'], ['code' => 'users.manage']]);
        $hrDir = Fixtures::tempPlugin('permissions', [['code' => 'hr.employees.view']]);
        $this->states = new ArrayStateStore();
        $this->ledger = new ArrayResourceLedger();
        $this->migrations = new RecordingMigrationRunner();
        $this->permissions = new RecordingHandler('permissions');
        $registry = new DeclarationRegistry();
        $registry->register($this->permissions);
        $this->cacheFile = sys_get_temp_dir() . '/lasabro-pm-' . bin2hex(random_bytes(4)) . '/lasabro.php';
        $source = new ArrayManifestSource([
            Fixtures::manifest('Lasabro.Core', [], '1.0.0', ['permissions'], [], $coreDir),
            Fixtures::manifest('Lasabro.Hr', ['Lasabro.Core' => '^1.0'], '1.0.0', ['permissions', 'menu'], [], $hrDir),
            Fixtures::manifest('Lasabro.Payroll', ['Lasabro.Hr' => '^1.0'], '1.0.0'),
        ]);
        $this->pm = new PluginManager($source, $this->states, $this->ledger, $this->migrations, $registry, new PluginCache(), $this->cacheFile);
    }

    private function cachedIds(): array
    {
        return array_column((new PluginCache())->read($this->cacheFile), 'id');
    }

    public function test_install_runs_migrations_for_the_plugin_and_its_requirements_but_does_not_activate(): void
    {
        $this->assertSame(['Lasabro.Core', 'Lasabro.Hr'], $this->pm->install('Lasabro.Hr'));
        $this->assertSame(['run:Lasabro.Core', 'run:Lasabro.Hr'], $this->migrations->log);
        $this->assertFalse($this->states->states['Lasabro.Hr']->active);
        $this->assertSame([], $this->permissions->log);
    }

    public function test_activation_is_refused_with_a_clear_message_when_a_requirement_is_off(): void
    {
        try {
            $this->pm->activate('Lasabro.Hr');
            $this->fail('expected refusal');
        } catch (PluginException $e) {
            $this->assertStringContainsString('Lasabro.Hr cannot be activated', $e->getMessage());
            $this->assertStringContainsString('Lasabro.Core', $e->getMessage());
            $this->assertStringContainsString('--with-dependencies', $e->getMessage());
        }
        $this->assertSame([], $this->cachedIds());
    }

    public function test_activating_with_dependencies_installs_syncs_and_caches_in_order(): void
    {
        $this->assertSame(['Lasabro.Core', 'Lasabro.Hr'], $this->pm->activate('Lasabro.Hr', withDependencies: true));
        $this->assertSame(['Lasabro.Core', 'Lasabro.Hr'], $this->cachedIds());
        $this->assertSame(['users.view', 'users.manage'], $this->ledger->codes('Lasabro.Core', 'permissions'));
        $this->assertContains('sync:Lasabro.Hr:hr.employees.view', $this->permissions->log);
        $this->assertContains('show:Lasabro.Hr:hr.employees.view', $this->permissions->log);
        $this->assertTrue($this->states->states['Lasabro.Hr']->active);
        // Hr declares "menu" but nobody handles it and the file is absent: reported, not fatal.
        $this->assertNotEmpty(array_filter($this->pm->warnings(), fn ($w) => str_contains($w, '"menu"')));
    }

    public function test_activating_twice_is_a_no_op(): void
    {
        $this->pm->activate('Lasabro.Core');
        $this->assertSame([], $this->pm->activate('Lasabro.Core'));
    }

    public function test_deactivation_is_blocked_while_another_active_plugin_needs_it(): void
    {
        $this->pm->activate('Lasabro.Payroll', withDependencies: true);
        $this->expectException(PluginException::class);
        $this->expectExceptionMessage('required by active Lasabro.Hr');
        $this->pm->deactivate('Lasabro.Core');
    }

    public function test_cascade_deactivation_hides_declarations_and_updates_the_boot_cache(): void
    {
        $this->pm->activate('Lasabro.Payroll', withDependencies: true);
        $this->assertSame(['Lasabro.Core', 'Lasabro.Hr', 'Lasabro.Payroll'], $this->cachedIds());
        $off = $this->pm->deactivate('Lasabro.Hr', cascade: true);
        $this->assertSame(['Lasabro.Payroll', 'Lasabro.Hr'], $off);
        $this->assertSame(['Lasabro.Core'], $this->cachedIds());
        $this->assertContains('hide:Lasabro.Hr:hr.employees.view', $this->permissions->log);
        $this->assertTrue($this->states->states['Lasabro.Hr']->active === false && isset($this->states->states['Lasabro.Hr']), 'data and state stay');
    }

    public function test_uninstall_needs_the_plugin_inactive_and_unused_and_purge_is_explicit(): void
    {
        $this->pm->activate('Lasabro.Hr', withDependencies: true);
        try {
            $this->pm->uninstall('Lasabro.Hr');
            $this->fail('active plugin must not be uninstalled');
        } catch (PluginException $e) {
            $this->assertStringContainsString('Deactivate it before uninstalling', $e->getMessage());
        }
        $this->pm->deactivate('Lasabro.Hr');
        $this->pm->deactivate('Lasabro.Core');
        try {
            $this->pm->uninstall('Lasabro.Core');
            $this->fail('Core is still required by an installed plugin');
        } catch (PluginException $e) {
            $this->assertStringContainsString('installed plugins still require it (Lasabro.Hr)', $e->getMessage());
        }
        $this->pm->uninstall('Lasabro.Hr');                       // keeps data
        $this->assertNotContains('rollback:Lasabro.Hr', $this->migrations->log);
        $this->assertArrayNotHasKey('Lasabro.Hr', $this->states->states);
        $this->assertSame([], $this->ledger->codes('Lasabro.Hr', 'permissions'));
        $this->pm->uninstall('Lasabro.Core', purge: true);
        $this->assertContains('rollback:Lasabro.Core', $this->migrations->log);
    }

    public function test_unknown_plugin_gives_an_actionable_message(): void
    {
        $this->expectException(PluginException::class);
        $this->expectExceptionMessage('Unknown plugin Lasabro.Nope');
        $this->pm->activate('Lasabro.Nope');
    }

    public function test_status_lists_platform_plugins_first_with_their_state(): void
    {
        $this->pm->activate('Lasabro.Core');
        $rows = $this->pm->status();
        $this->assertSame('Lasabro.Core', $rows[0]['id']);
        $this->assertSame('active', $rows[0]['state']);
        $this->assertSame('not installed', $rows[2]['state']);
    }

    public function test_history_is_recorded(): void
    {
        $this->pm->activate('Lasabro.Hr', withDependencies: true);
        $this->pm->deactivate('Lasabro.Hr');
        $this->assertSame(['Lasabro.Core:installed', 'Lasabro.Core:activated', 'Lasabro.Hr:installed', 'Lasabro.Hr:activated', 'Lasabro.Hr:deactivated'], $this->states->events);
    }
}
