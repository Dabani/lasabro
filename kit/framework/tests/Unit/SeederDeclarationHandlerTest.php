<?php

declare(strict_types=1);

namespace Lasabro\Framework\Tests\Unit;

use Lasabro\Framework\Support\SeederDeclarationHandler;
use Lasabro\Framework\Tests\Support\ArrayResourceLedger;
use Lasabro\Framework\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

class SeederDeclarationHandlerTest extends TestCase
{
    public function test_runs_each_seeder_once_in_order_and_remembers_them(): void
    {
        $ran = [];
        $ledger = new ArrayResourceLedger();
        $h = new SeederDeclarationHandler($ledger, false, function (string $c) use (&$ran) { $ran[] = $c; });
        $plugin = Fixtures::manifest('Lasabro.Procurement');

        $codes = $h->sync($plugin, ['A\\Seed1', 'A\\Seed2']);
        $this->assertSame(['A\\Seed1', 'A\\Seed2'], $ran);
        $this->assertSame(['A\\Seed1', 'A\\Seed2'], $codes);

        $ledger->record('Lasabro.Procurement', 'seeders', $codes);
        $h->sync($plugin, ['A\\Seed1', 'A\\Seed2', 'A\\Seed3']);       // a later plugin version adds one seeder
        $this->assertSame(['A\\Seed1', 'A\\Seed2', 'A\\Seed3'], $ran);
    }

    public function test_adopt_mode_records_without_running(): void
    {
        $ran = [];
        $h = new SeederDeclarationHandler(new ArrayResourceLedger(), true, function (string $c) use (&$ran) { $ran[] = $c; });
        $codes = $h->sync(Fixtures::manifest('Lasabro.Procurement'), ['A\\Seed1']);
        $this->assertSame([], $ran);
        $this->assertSame(['A\\Seed1'], $codes);
    }

    public function test_a_failing_seeder_stops_the_activation(): void
    {
        $h = new SeederDeclarationHandler(new ArrayResourceLedger(), false, function (string $c) { throw new \RuntimeException('boom'); });
        $this->expectException(\RuntimeException::class);
        $h->sync(Fixtures::manifest('Lasabro.Procurement'), ['A\\Seed1']);
    }
}
