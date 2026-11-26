<?php

/**
 * Routes of this plugin. The framework mounts this file inside the authenticated, organization-scoped group
 * (prefix and middleware come from config/lasabro.php), exactly where the application's routes/api.php used to include them.
 */
require __DIR__ . '/procurement.php';
require __DIR__ . '/procurement-orders.php';
require __DIR__ . '/procurement-rfqs.php';
require __DIR__ . '/procurement-files.php';
require __DIR__ . '/procurement-invoices.php';
