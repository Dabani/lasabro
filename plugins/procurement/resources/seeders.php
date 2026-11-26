<?php

use Lasabro\Procurement\Database\Seeders\ProcurementSeeder;
use Lasabro\Procurement\Database\Seeders\ProcurementOrdersSeeder;
use Lasabro\Procurement\Database\Seeders\ProcurementInvoicesSeeder;
use Lasabro\Procurement\Database\Seeders\ProcurementRfqSeeder;
use Lasabro\Procurement\Database\Seeders\ProcurementFilesSeeder;

/** Seeders run once, in this order, when the plugin is first activated (skipped with --adopt). */
return [ProcurementSeeder::class, ProcurementOrdersSeeder::class, ProcurementInvoicesSeeder::class, ProcurementRfqSeeder::class, ProcurementFilesSeeder::class];
