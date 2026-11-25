<?php

namespace Lasabro\Framework\Console;

use Illuminate\Console\Command;
use Lasabro\Framework\Contracts\ManifestSource;
use Lasabro\Framework\Plugin\PluginException;
use Lasabro\Framework\Plugin\PluginManager;
use Lasabro\Framework\Support\ComposerManifestSource;

/** Shared behaviour of the lasabro:plugin:* commands: refusals are shown as plain messages, never as stack traces. */
abstract class PluginCommand extends Command
{
    /** @param callable(PluginManager):int $work */
    protected function guarded(PluginManager $pm, callable $work): int
    {
        try {
            $code = $work($pm);
        } catch (PluginException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        foreach ($pm->warnings() as $warning) {
            $this->warn($warning);
        }

        return $code;
    }

    protected function reportSourceErrors(): void
    {
        $source = $this->laravel->make(ManifestSource::class);
        if ($source instanceof ComposerManifestSource) {
            foreach ($source->errors() as $e) {
                $this->warn($e);
            }
        }
    }
}
