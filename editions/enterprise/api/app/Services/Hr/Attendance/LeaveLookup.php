<?php

namespace App\Services\Hr\Attendance;

/**
 * Is the employee on approved leave on a date? Attendance asks this so that leave is never reported as an absence.
 * Rebinds this interface to its own implementation; until then nobody is on leave.
 */
interface LeaveLookup
{
    public function onApprovedLeave(string $employeeId, string $date): bool;
}
