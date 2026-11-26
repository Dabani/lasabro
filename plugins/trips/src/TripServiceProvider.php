<?php

namespace Lasabro\Trips;

use Illuminate\Console\Scheduling\Schedule;
use Lasabro\Framework\PluginServiceProvider;
use Lasabro\Trips\Console\TripsRemind;
use Lasabro\Trips\Services\TripApprovalHandler;

/** Trip approval handler and permission, code prefixes (trip, mission authorization), daily reminder of unretired advances. */
class TripServiceProvider extends PluginServiceProvider
{
    protected function bootPlugin(): void
    {
        config([
            'cops.approval_handlers' => array_merge(config('cops.approval_handlers', []), ['trip' => TripApprovalHandler::class]),
            'cops.approval_permissions' => array_merge(config('cops.approval_permissions', []), ['trip' => 'hr.trip.approve']),
            'cops.code_prefixes' => array_merge(config('cops.code_prefixes', []), ['trip' => 'TRP', 'trip_authorization' => 'MA']),
        ]);
        $this->commands([TripsRemind::class]);
        $this->callAfterResolving(Schedule::class, fn (Schedule $s) => $s->command('cops:trips-remind')->dailyAt('08:00')->withoutOverlapping());
    }
}
