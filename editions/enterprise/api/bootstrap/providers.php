<?php

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseServiceProvider;

return [
    App\Providers\PortalServiceProvider::class,
    App\Providers\BillingServiceProvider::class,
    App\Providers\TimeServiceProvider::class,
    App\Providers\ProjectsServiceProvider::class,
    App\Providers\CopsFoundationServiceProvider::class,
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
    App\Providers\LasabroHostServiceProvider::class,
    App\Providers\HrServiceProvider::class,
    App\Providers\MeetingsServiceProvider::class,
    App\Providers\PayrollServiceProvider::class,
    App\Providers\LeaveServiceProvider::class,
    App\Providers\TripServiceProvider::class,
];
