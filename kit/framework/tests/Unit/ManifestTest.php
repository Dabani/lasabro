<?php

declare(strict_types=1);

namespace Lasabro\Framework\Tests\Unit;

use Lasabro\Framework\Plugin\Manifest;
use Lasabro\Framework\Plugin\PluginException;
use PHPUnit\Framework\TestCase;

class ManifestTest extends TestCase
{
    private function composer(array $overrides = []): array
    {
        return ['name' => 'lasabro/plugin-manager', 'extra' => ['lasabro' => array_merge([
            'id' => 'Lasabro.PluginManager', 'title' => 'Plugin Manager', 'tier' => 'platform',
            'provider' => 'Lasabro\\PluginManager\\PluginManagerServiceProvider',
            'requires' => ['Lasabro.Core' => '^1.0'], 'suggests' => ['Lasabro.Reports' => 'plugin usage report'],
            'frontends' => ['react' => '@lasabro/plugin-manager-react'], 'declares' => ['permissions', 'menu'],
        ], $overrides)]];
    }

    public function test_reads_a_complete_manifest(): void
    {
        $m = Manifest::fromComposer($this->composer(), '/p/plugin-manager', '1.2.0');
        $this->assertSame('Lasabro.PluginManager', $m->id);
        $this->assertSame('1.2.0', $m->version);
        $this->assertSame(['Lasabro.Core' => '^1.0'], $m->requires);
        $this->assertSame(['permissions', 'menu'], $m->declares);
        $this->assertSame('plugin-manager', $m->slug());
    }

    public function test_slug_of_multi_word_names(): void
    {
        $m = Manifest::fromComposer($this->composer(['id' => 'Lasabro.ItInfrastructure']), '/p');
        $this->assertSame('it-infrastructure', $m->slug());
    }

    public function test_rejects_an_id_that_is_not_vendor_dot_name(): void
    {
        $this->expectException(PluginException::class);
        Manifest::fromComposer($this->composer(['id' => 'lasabro-hr']), '/p');
    }

    public function test_rejects_unknown_tier_and_missing_provider_and_self_dependency(): void
    {
        foreach ([['tier' => 'magic'], ['provider' => ''], ['requires' => ['Lasabro.PluginManager' => '^1.0']], ['declares' => ['Bad Type']]] as $bad) {
            try {
                Manifest::fromComposer($this->composer($bad), '/p');
                $this->fail('expected a PluginException for ' . json_encode($bad));
            } catch (PluginException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_package_without_manifest_is_refused(): void
    {
        $this->expectException(PluginException::class);
        Manifest::fromComposer(['name' => 'x/y'], '/p');
    }
}
