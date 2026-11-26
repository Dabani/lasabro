<?php

namespace Lasabro\Leave;

use App\Services\Hr\Attendance\LeaveLookup;
use Illuminate\Console\Scheduling\Schedule;
use Lasabro\Framework\PluginServiceProvider;
use Lasabro\Leave\Console\LeaveAccrue;
use Lasabro\Leave\Console\LeaveYearEnd;
use Lasabro\Leave\Services\DbLeaveLookup;
use Lasabro\Leave\Services\LeaveApprovalHandler;
use Lasabro\Leave\Services\LeavePolicyResolver;

/** Leave approval handler and permission, request code prefix, attendance leave lookup, daily accrual and year-end jobs. */
class LeaveServiceProvider extends PluginServiceProvider
{
    protected function registerPlugin(): void
    {
        $this->app->bind(LeaveLookup::class, DbLeaveLookup::class);        // registered before HrServiceProvider's bindIf(NullLeaveLookup) runs
        $this->app->scoped(LeavePolicyResolver::class);                    // its statutory-rule cache lives for one request or job only
    }

    protected function bootPlugin(): void
    {
        config([
            'cops.approval_handlers' => array_merge(config('cops.approval_handlers', []), ['leave' => LeaveApprovalHandler::class]),
            'cops.approval_permissions' => array_merge(config('cops.approval_permissions', []), ['leave' => 'hr.leave.approve']),
            'cops.code_prefixes' => array_merge(config('cops.code_prefixes', []), ['leave_request' => 'LVE']),
        ]);
        $this->commands([LeaveAccrue::class, LeaveYearEnd::class]);
        $this->callAfterResolving(Schedule::class, function (Schedule $s) {
            $s->command('cops:leave-accrue')->dailyAt('01:30')->withoutOverlapping();       // idempotent: a missed day catches up
            $s->command('cops:leave-year-end')->dailyAt('02:00')->withoutOverlapping();
        });
    }
}
