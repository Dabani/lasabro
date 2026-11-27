<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;
use App\Providers\CopsFoundationServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\HrServiceProvider;
use App\Providers\LasabroHostServiceProvider;
use App\Providers\MeetingsServiceProvider;
use App\Providers\PayrollServiceProvider;
use App\Providers\PortalServiceProvider;
use App\Providers\TimeServiceProvider;
use Lasabro\Projects\ProjectsServiceProvider;

return [
    PortalServiceProvider::class,
    BillingServiceProvider::class,
    TimeServiceProvider::class,
    ProjectsServiceProvider::class,
    CopsFoundationServiceProvider::class,
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
    LasabroHostServiceProvider::class,
    HrServiceProvider::class,
    MeetingsServiceProvider::class,
    PayrollServiceProvider::class,
];
