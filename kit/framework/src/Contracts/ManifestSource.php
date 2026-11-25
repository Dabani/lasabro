<?php

declare(strict_types=1);

namespace Lasabro\Framework\Contracts;

use Lasabro\Framework\Plugin\Manifest;

/** Finds the plugins whose packages are present on this machine (installed by Composer or found in a local folder). */
interface ManifestSource
{
    /** @return array<string,Manifest> keyed by plugin id */
    public function manifests(): array;
}
