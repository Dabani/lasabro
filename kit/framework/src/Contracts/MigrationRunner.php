<?php

declare(strict_types=1);

namespace Lasabro\Framework\Contracts;

use Lasabro\Framework\Plugin\Manifest;

interface MigrationRunner
{
    /** Run the plugin's pending migrations (database/migrations inside its folder). Safe to call repeatedly. */
    public function run(Manifest $plugin): void;

    /** Roll the plugin's migrations back. Destroys the plugin's data; only used by an explicit purge. */
    public function rollback(Manifest $plugin): void;
}
