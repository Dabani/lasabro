<?php

namespace Lasabro\Leave\Services;

use App\Services\Hr\Attendance\LeaveLookup;
use Lasabro\Leave\Models\LeaveRequestDay;

/** Replaces NullLeaveLookup: attendance never reports a full day of approved leave as an absence. Half days are not "on leave" for the whole day. */
class DbLeaveLookup implements LeaveLookup
{
    public function onApprovedLeave(string $employeeId, string $date): bool
    {
        return LeaveRequestDay::withoutGlobalScopes()->where('employee_id', $employeeId)->whereDate('leave_date', $date)->where('fraction', '>=', 1)->exists();
    }
}
