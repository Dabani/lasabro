<?php

declare(strict_types=1);

namespace Lasabro\Framework\Tests\Unit;

use Lasabro\Framework\Plugin\ActivationReport;
use Lasabro\Framework\Plugin\DependencyResolver;
use Lasabro\Framework\Plugin\PluginException;
use Lasabro\Framework\Plugin\PluginState;
use Lasabro\Framework\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

class DependencyResolverTest extends TestCase
{
    /** Core <- Approvals <- Hr <- Payroll (also needs Attendance, Rules) */
    private function catalogue(array $active = [], array $versions = []): DependencyResolver
    {
        $v = fn ($id) => $versions[$id] ?? '1.0.0';
        $list = [
            Fixtures::manifest('Lasabro.Core', [], $v('Lasabro.Core')),
            Fixtures::manifest('Lasabro.Approvals', ['Lasabro.Core' => '^1.0'], $v('Lasabro.Approvals')),
            Fixtures::manifest('Lasabro.Rules', ['Lasabro.Core' => '^1.0', 'Lasabro.Approvals' => '^1.0'], $v('Lasabro.Rules')),
            Fixtures::manifest('Lasabro.Hr', ['Lasabro.Core' => '^1.0', 'Lasabro.Approvals' => '^1.0'], $v('Lasabro.Hr')),
            Fixtures::manifest('Lasabro.Attendance', ['Lasabro.Hr' => '^1.0'], $v('Lasabro.Attendance')),
            Fixtures::manifest('Lasabro.Payroll', ['Lasabro.Hr' => '^1.0', 'Lasabro.Attendance' => '^1.0', 'Lasabro.Rules' => '^1.0'], $v('Lasabro.Payroll'),
                [], ['Lasabro.Leave' => 'unpaid leave deductions']),
        ];
        $manifests = [];
        foreach ($list as $m) {
            $manifests[$m->id] = $m;
        }
        $states = [];
        foreach ($active as $id) {
            $states[$id] = new PluginState($id, '1.0.0', true);
        }

        return new DependencyResolver($manifests, $states);
    }

    public function test_activation_is_blocked_by_inactive_requirements(): void
    {
        $r = $this->catalogue(['Lasabro.Core'])->activationReport('Lasabro.Hr');
        $this->assertFalse($r->ok());
        $this->assertSame(['Lasabro.Approvals'], $r->problemsOfKind(ActivationReport::INACTIVE));
        $this->assertStringContainsString('Lasabro.Approvals is installed but inactive', $r->message());
    }

    public function test_activation_is_allowed_when_all_requirements_are_active(): void
    {
        $this->assertTrue($this->catalogue(['Lasabro.Core', 'Lasabro.Approvals'])->activationReport('Lasabro.Hr')->ok());
    }

    public function test_a_version_that_does_not_satisfy_is_reported_with_both_versions(): void
    {
        $r = $this->catalogue(['Lasabro.Core', 'Lasabro.Approvals'], ['Lasabro.Core' => '2.0.0'])->activationReport('Lasabro.Hr');
        $this->assertSame(['Lasabro.Core'], $r->problemsOfKind(ActivationReport::VERSION));
        $this->assertStringContainsString('Lasabro.Core 2.0.0 does not satisfy ^1.0', $r->message());
    }

    public function test_development_checkouts_satisfy_any_constraint(): void
    {
        $this->assertTrue(DependencyResolver::satisfies('dev-main', '^1.0'));
        $this->assertTrue(DependencyResolver::satisfies('1.x-dev', '^1.0'));
        $this->assertTrue(DependencyResolver::satisfies('1.4.2', '^1.0'));
        $this->assertFalse(DependencyResolver::satisfies('0.9.0', '^1.0'));
    }

    public function test_a_requirement_that_is_not_on_this_system_is_missing(): void
    {
        $r = new DependencyResolver(['Lasabro.Hr' => Fixtures::manifest('Lasabro.Hr', ['Lasabro.Core' => '^1.0'])], []);
        $this->assertSame(['Lasabro.Core'], $r->activationReport('Lasabro.Hr')->problemsOfKind(ActivationReport::MISSING));
    }

    public function test_plan_lists_every_inactive_dependency_in_dependency_order(): void
    {
        $plan = $this->catalogue(['Lasabro.Core'])->activationPlan('Lasabro.Payroll');
        $this->assertTrue($plan['report']->ok());
        $this->assertSame(['Lasabro.Approvals', 'Lasabro.Hr', 'Lasabro.Attendance', 'Lasabro.Rules', 'Lasabro.Payroll'], $plan['order']);
    }

    public function test_plan_cannot_fix_a_missing_or_incompatible_dependency(): void
    {
        $plan = $this->catalogue(['Lasabro.Core'], ['Lasabro.Rules' => '0.5.0'])->activationPlan('Lasabro.Payroll');
        $this->assertFalse($plan['report']->ok());
        $this->assertSame(['Lasabro.Rules'], $plan['report']->problemsOfKind(ActivationReport::VERSION));
    }

    public function test_deactivation_cascade_switches_off_dependents_first(): void
    {
        $all = ['Lasabro.Core', 'Lasabro.Approvals', 'Lasabro.Rules', 'Lasabro.Hr', 'Lasabro.Attendance', 'Lasabro.Payroll'];
        $r = $this->catalogue($all);
        $this->assertSame(['Lasabro.Attendance', 'Lasabro.Payroll'], $r->activeDependents('Lasabro.Hr'));
        $cascade = $r->deactivationCascade('Lasabro.Hr');
        $this->assertSame('Lasabro.Hr', end($cascade));
        $this->assertLessThan(array_search('Lasabro.Hr', $cascade), array_search('Lasabro.Payroll', $cascade));
        $this->assertLessThan(array_search('Lasabro.Attendance', $cascade), array_search('Lasabro.Payroll', $cascade));
        $this->assertNotContains('Lasabro.Core', $cascade);
    }

    public function test_order_is_dependencies_first_and_deterministic(): void
    {
        $o = $this->catalogue()->order(['Lasabro.Payroll', 'Lasabro.Hr', 'Lasabro.Core', 'Lasabro.Approvals', 'Lasabro.Attendance', 'Lasabro.Rules']);
        $this->assertSame(['Lasabro.Core', 'Lasabro.Approvals', 'Lasabro.Hr', 'Lasabro.Rules', 'Lasabro.Attendance', 'Lasabro.Payroll'], $o);
    }

    public function test_a_dependency_cycle_is_detected(): void
    {
        $a = Fixtures::manifest('Lasabro.A', ['Lasabro.B' => '*']);
        $b = Fixtures::manifest('Lasabro.B', ['Lasabro.A' => '*']);
        $r = new DependencyResolver(['Lasabro.A' => $a, 'Lasabro.B' => $b], []);
        $this->expectException(PluginException::class);
        $r->order(['Lasabro.A', 'Lasabro.B']);
    }

    public function test_the_notice_is_a_clear_sentence_naming_what_is_required(): void
    {
        $notice = $this->catalogue(['Lasabro.Core'])->notice('Lasabro.Payroll');
        $this->assertStringContainsString('Lasabro.Payroll requires Lasabro.Hr ^1.0, Lasabro.Attendance ^1.0 and Lasabro.Rules ^1.0.', $notice);
        $this->assertStringContainsString('Lasabro.Hr is installed but inactive', $notice);
        $this->assertStringContainsString('Works better with Lasabro.Leave (unpaid leave deductions).', $notice);
        $this->assertStringContainsString('Required by Lasabro.Attendance and Lasabro.Payroll.', $this->catalogue()->notice('Lasabro.Hr'));
    }
}
