<?php

namespace Lasabro\Framework\Support;

use Composer\InstalledVersions;
use Lasabro\Framework\Contracts\ManifestSource;
use Lasabro\Framework\Plugin\Manifest;
use Lasabro\Framework\Plugin\PluginException;

/**
 * Finds plugins in two places: Composer packages of the plugin type (what `composer require lasabro/hr` installs,
 * including path repositories in a monorepo) and any extra folders listed in config('lasabro.plugin_paths').
 * A package with a broken manifest is skipped and reported through errors(), so one bad plugin cannot stop the rest.
 */
final class ComposerManifestSource implements ManifestSource
{
    /** @var list<string> */
    private array $errors = [];

    /** @param list<string> $extraPaths */
    public function __construct(private readonly string $packageType, private readonly array $extraPaths = [])
    {
    }

    public function manifests(): array
    {
        $found = [];
        foreach ($this->candidates() as [$dir, $version]) {
            $file = $dir . '/composer.json';
            if (! is_file($file)) {
                continue;
            }
            try {
                $composer = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                $m = Manifest::fromComposer($composer, realpath($dir) ?: $dir, $version);
            } catch (\JsonException $e) {
                $this->errors[] = "{$file}: invalid JSON ({$e->getMessage()}).";
                continue;
            } catch (PluginException $e) {
                $this->errors[] = $e->getMessage();
                continue;
            }
            if (isset($found[$m->id]) && $found[$m->id]->path !== $m->path) {
                $this->errors[] = "{$m->id} is provided twice ({$found[$m->id]->path} and {$m->path}); the first one is used.";
                continue;
            }
            $found[$m->id] = $m;
        }
        ksort($found);

        return $found;
    }

    /** Problems found while reading manifests. @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return iterable<array{0:string,1:?string}> directory and version */
    private function candidates(): iterable
    {
        if (class_exists(InstalledVersions::class)) {
            foreach (InstalledVersions::getInstalledPackagesByType($this->packageType) as $package) {
                $path = InstalledVersions::getInstallPath($package);
                if ($path !== null) {
                    yield [$path, InstalledVersions::getPrettyVersion($package)];
                }
            }
        }
        foreach ($this->extraPaths as $base) {
            foreach (glob(rtrim($base, '/\\') . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                yield [$dir, null];
            }
        }
    }
}
