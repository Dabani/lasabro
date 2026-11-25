<?php

declare(strict_types=1);

namespace Lasabro\Framework\Tests\Unit;

use Lasabro\Framework\Plugin\PluginCache;
use PHPUnit\Framework\TestCase;

class PluginCacheTest extends TestCase
{
    public function test_round_trip_keeps_order(): void
    {
        $file = sys_get_temp_dir() . '/lasabro-cache-' . bin2hex(random_bytes(4)) . '/lasabro.php';
        $cache = new PluginCache();
        $entries = [
            ['id' => 'Lasabro.Core', 'provider' => 'Lasabro\\Core\\CoreServiceProvider', 'path' => '/p/core'],
            ['id' => 'Lasabro.Hr', 'provider' => 'Lasabro\\Hr\\HrServiceProvider', 'path' => '/p/hr'],
        ];
        $cache->write($file, $entries);
        $this->assertSame($entries, $cache->read($file));
        $cache->forget($file);
        $this->assertSame([], $cache->read($file));
    }

    public function test_reading_a_missing_cache_is_not_an_error(): void
    {
        $this->assertSame([], (new PluginCache())->read('/nonexistent/lasabro.php'));
    }
}
