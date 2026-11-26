<?php

namespace Lasabro\Leave\Console;

use Illuminate\Console\Command;
use Lasabro\Leave\Services\LeaveBalanceService;

class LeaveAccrue extends Command
{
    protected $signature = 'cops:leave-accrue';
    protected $description = 'Accrue leave for every completed month of the current leave year that is not accrued yet (safe to repeat)';

    public function handle(LeaveBalanceService $svc): int
    {
        $this->info($svc->accrueDue() . ' accrual(s) posted.');
        return self::SUCCESS;
    }
}
