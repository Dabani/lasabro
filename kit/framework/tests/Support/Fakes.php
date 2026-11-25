<?php

declare(strict_types=1);

namespace Lasabro\Framework\Tests\Support;

use Lasabro\Framework\Contracts\DeclarationHandler;
use Lasabro\Framework\Contracts\ManifestSource;
use Lasabro\Framework\Contracts\MigrationRunner;
use Lasabro\Framework\Contracts\ResourceLedger;
use Lasabro\Framework\Contracts\StateStore;
use Lasabro\Framework\Plugin\Manifest;
use Lasabro\Framework\Plugin\PluginState;

final class Fixtures
{
    /** @param array<string,string> $requires @param list<string> $declares */
    public static function manifest(string $id, array $requires = [], string $version = '1.0.0', array $declares = [], array $suggests = [], ?string $dir = null): Manifest
    {
        $slug = strtolower(substr($id, (int) strpos($id, '.') + 1));

        return new Manifest($id, 'lasabro/' . $slug, $version, $id, str_ends_with($id, 'Core') ? 'platform' : 'domain', 'Lasabro\\X\\' . $slug . 'Provider', $requires, $suggests, [], $declares, $dir ?? sys_get_temp_dir());
    }

    public static function tempPlugin(string $type, array $rows): string
    {
        $dir = sys_get_temp_dir() . '/lasabro-test-' . bin2hex(random_bytes(4));
        mkdir($dir . '/resources', 0775, true);
        file_put_contents($dir . '/resources/' . $type . '.php', '<?php return ' . var_export($rows, true) . ';');

        return $dir;
    }
}

final class ArrayManifestSource implements ManifestSource
{
    /** @param list<Manifest> $list */
    public function __construct(private array $list)
    {
    }

    public function manifests(): array
    {
        $out = [];
        foreach ($this->list as $m) {
            $out[$m->id] = $m;
        }

        return $out;
    }
}

final class ArrayStateStore implements StateStore
{
    /** @var array<string,PluginState> */
    public array $states = [];
    /** @var list<string> */
    public array $events = [];

    public function all(): array
    {
        return $this->states;
    }

    public function save(PluginState $state): void
    {
        $this->states[$state->id] = $state;
    }

    public function forget(string $pluginId): void
    {
        unset($this->states[$pluginId]);
    }

    public function event(string $pluginId, string $action, ?string $detail = null): void
    {
        $this->events[] = "{$pluginId}:{$action}";
    }
}

final class ArrayResourceLedger implements ResourceLedger
{
    /** @var array<string,array<string,list<string>>> */
    public array $data = [];

    public function record(string $pluginId, string $type, array $codes): void
    {
        $this->data[$pluginId][$type] = $codes;
    }

    public function codes(string $pluginId, string $type): array
    {
        return $this->data[$pluginId][$type] ?? [];
    }

    public function clear(string $pluginId): void
    {
        unset($this->data[$pluginId]);
    }
}

final class RecordingMigrationRunner implements MigrationRunner
{
    /** @var list<string> */
    public array $log = [];

    public function run(Manifest $plugin): void
    {
        $this->log[] = 'run:' . $plugin->id;
    }

    public function rollback(Manifest $plugin): void
    {
        $this->log[] = 'rollback:' . $plugin->id;
    }
}

final class RecordingHandler implements DeclarationHandler
{
    /** @var list<string> */
    public array $log = [];

    public function __construct(private string $type = 'permissions')
    {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function sync(Manifest $plugin, array $items): array
    {
        $codes = array_map(fn ($i) => $i['code'], $items);
        $this->log[] = 'sync:' . $plugin->id . ':' . implode(',', $codes);

        return $codes;
    }

    public function setActive(Manifest $plugin, bool $active, array $codes): void
    {
        $this->log[] = ($active ? 'show:' : 'hide:') . $plugin->id . ':' . implode(',', $codes);
    }

    public function remove(Manifest $plugin, array $codes): void
    {
        $this->log[] = 'remove:' . $plugin->id . ':' . implode(',', $codes);
    }
}
