<?php

namespace Tests\Unit;

use App\Support\Fingerprint\FingerprintDiff;
use PHPUnit\Framework\TestCase;

class FingerprintDiffTest extends TestCase
{
    public function test_identical_fingerprints_have_no_difference(): void
    {
        $fp = ['sections' => ['routes' => ['GET a | perm:x'], 'menu' => ['m1']]];
        $this->assertSame([], FingerprintDiff::compare($fp, $fp));
    }

    public function test_added_and_removed_lines_are_reported_per_section(): void
    {
        $a = ['sections' => ['routes' => ['GET a | perm:x', 'GET b | perm:y']]];
        $b = ['sections' => ['routes' => ['GET a | perm:x', 'GET c | perm:z']]];
        $diff = FingerprintDiff::compare($a, $b);

        $this->assertSame(['GET b | perm:y'], $diff['routes']['removed']);
        $this->assertSame(['GET c | perm:z'], $diff['routes']['added']);
        $this->assertTrue(FingerprintDiff::hasBlockingDifferences($diff));
    }

    public function test_a_changed_permission_guard_on_a_route_is_blocking(): void
    {
        $a = ['sections' => ['routes' => ['GET a | perm:x']]];
        $b = ['sections' => ['routes' => ['GET a | perm:other']]];
        $this->assertTrue(FingerprintDiff::hasBlockingDifferences(FingerprintDiff::compare($a, $b)));
    }

    public function test_moving_a_controller_is_informational_unless_strict(): void
    {
        $a = ['sections' => ['route_actions' => ['GET a => UserController@index']]];
        $b = ['sections' => ['route_actions' => ['GET a => Lasabro\\Core\\UserController@index']]];
        $diff = FingerprintDiff::compare($a, $b);

        $this->assertArrayHasKey('route_actions', $diff);
        $this->assertFalse(FingerprintDiff::hasBlockingDifferences($diff));
        $this->assertTrue(FingerprintDiff::hasBlockingDifferences($diff, strict: true));
    }

    public function test_a_section_missing_from_one_side_counts_as_all_added_or_removed(): void
    {
        $diff = FingerprintDiff::compare(['sections' => []], ['sections' => ['menu' => ['m1', 'm2']]]);
        $this->assertSame(['m1', 'm2'], $diff['menu']['added']);
        $this->assertSame([], $diff['menu']['removed']);
    }

    public function test_line_order_and_duplicates_do_not_matter(): void
    {
        $a = ['sections' => ['menu' => ['b', 'a', 'a']]];
        $b = ['sections' => ['menu' => ['a', 'b']]];
        $this->assertSame([], FingerprintDiff::compare($a, $b));
    }
}
