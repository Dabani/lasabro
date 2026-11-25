<?php

declare(strict_types=1);

namespace Lasabro\Framework\Tests\Unit;

use Lasabro\Framework\Support\ComposerManifestSource;
use PHPUnit\Framework\TestCase;

class ComposerManifestSourceTest extends TestCase
{
    private function plugin(string $base, string $folder, array|string $composer): void
    {
        mkdir("{$base}/{$folder}", 0775, true);
        file_put_contents("{$base}/{$folder}/composer.json", is_string($composer) ? $composer : json_encode($composer));
    }

    private function manifest(string $id, array $extra = []): array
    {
        return ['name' => 'lasabro/' . strtolower(substr($id, 8)), 'type' => 'lasabro-plugin',
            'extra' => ['lasabro' => array_merge(['id' => $id, 'provider' => 'X\\' . substr($id, 8) . 'Provider'], $extra)]];
    }

    public function test_finds_plugins_in_extra_folders_and_reports_broken_ones_without_stopping(): void
    {
        $base = sys_get_temp_dir() . '/lasabro-src-' . bin2hex(random_bytes(4));
        $this->plugin($base, 'hr', $this->manifest('Lasabro.Hr', ['requires' => ['Lasabro.Core' => '^1.0']]));
        $this->plugin($base, 'core', $this->manifest('Lasabro.Core', ['tier' => 'platform']));
        $this->plugin($base, 'broken-json', '{ not json');
        $this->plugin($base, 'no-manifest', ['name' => 'x/y']);
        $this->plugin($base, 'bad-id', $this->manifest('hr-plugin'));

        $source = new ComposerManifestSource('lasabro-plugin', [$base]);
        $found = $source->manifests();

        $this->assertSame(['Lasabro.Core', 'Lasabro.Hr'], array_keys($found));
        $this->assertSame('dev-main', $found['Lasabro.Hr']->version);
        $this->assertSame(3, count($source->errors()));
    }

    public function test_the_same_plugin_in_two_places_is_reported_and_the_first_wins(): void
    {
        $a = sys_get_temp_dir() . '/lasabro-src-' . bin2hex(random_bytes(4));
        $b = sys_get_temp_dir() . '/lasabro-src-' . bin2hex(random_bytes(4));
        $this->plugin($a, 'hr', $this->manifest('Lasabro.Hr'));
        $this->plugin($b, 'hr', $this->manifest('Lasabro.Hr'));

        $source = new ComposerManifestSource('lasabro-plugin', [$a, $b]);
        $this->assertSame(1, count($source->manifests()));
        $this->assertStringContainsString('Lasabro.Hr is provided twice', $source->errors()[0]);
    }
}
