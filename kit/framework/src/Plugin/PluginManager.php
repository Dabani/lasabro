<?php

declare(strict_types=1);

namespace Lasabro\Framework\Plugin;

use Lasabro\Framework\Contracts\ManifestSource;
use Lasabro\Framework\Contracts\MigrationRunner;
use Lasabro\Framework\Contracts\ResourceLedger;
use Lasabro\Framework\Contracts\StateStore;

/**
 * Plugin lifecycle: install -> activate <-> deactivate -> uninstall.
 *
 *  install    migrations run, plugin recorded as installed (still switched off)
 *  activate   dependencies checked, declarations (permissions, menu...) synced, plugin switched on, boot cache rebuilt
 *  deactivate nothing that another active plugin needs may be switched off; declarations hidden, data untouched
 *  uninstall  only while inactive and unused; data are deleted only with $purge
 */
final class PluginManager
{
    /** @var array<string,Manifest>|null */
    private ?array $manifests = null;

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly ManifestSource $source,
        private readonly StateStore $states,
        private readonly ResourceLedger $ledger,
        private readonly MigrationRunner $migrations,
        private readonly DeclarationRegistry $declarations,
        private readonly PluginCache $cache,
        private readonly string $cacheFile,
        /** @var array<string,string> capabilities the host application provides: plugin id => version */
        private readonly array $provided = [],
    ) {
    }

    /** @return array<string,string> */
    public function provided(): array
    {
        return $this->provided;
    }

    /** @return array<string,Manifest> */
    public function manifests(): array
    {
        return $this->manifests ??= $this->source->manifests();
    }

    public function manifest(string $id): Manifest
    {
        return $this->manifests()[$id] ?? throw new PluginException("Unknown plugin {$id}. Is its package installed (composer require) or in a configured plugin folder?");
    }

    public function resolver(): DependencyResolver
    {
        return new DependencyResolver($this->manifests(), $this->states->all(), $this->provided);
    }

    /** Messages about things that were skipped (for example a declared resource type nobody handles). @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return list<array{id:string,title:string,tier:string,version:string,state:string,requires:array<string,string>}> */
    public function status(): array
    {
        $states = $this->states->all();
        $rows = [];
        foreach ($this->manifests() as $id => $m) {
            $rows[] = [
                'id' => $id, 'title' => $m->title, 'tier' => $m->tier, 'version' => $m->version, 'requires' => $m->requires,
                'state' => ! isset($states[$id]) ? 'not installed' : ($states[$id]->active ? 'active' : 'inactive'),
            ];
        }
        foreach ($this->provided as $id => $version) {
            $rows[] = ['id' => $id, 'title' => '(provided by the host application)', 'tier' => 'platform', 'version' => $version, 'requires' => [], 'state' => 'provided'];
        }
        usort($rows, fn ($a, $b) => [$a['tier'] === 'platform' ? 0 : 1, $a['id']] <=> [$b['tier'] === 'platform' ? 0 : 1, $b['id']]);

        return $rows;
    }

    /**
     * Install a plugin and the plugins it requires that are not installed yet (all still switched off).
     *
     * @return list<string> ids installed by this call
     */
    public function install(string $id): array
    {
        $this->manifest($id);
        $resolver = $this->resolver();
        $done = [];
        foreach ($resolver->order($this->closure($id)) as $pid) {
            if (! $resolver->isInstalled($pid)) {
                $this->installOne($pid);
                $done[] = $pid;
            }
        }

        return $done;
    }

    /**
     * @param bool $withDependencies also install and activate the inactive plugins it requires
     * @return list<string> ids switched on by this call (empty when it was already active)
     */
    public function activate(string $id, bool $withDependencies = false): array
    {
        $this->manifest($id);
        $resolver = $this->resolver();
        if ($resolver->isActive($id)) {
            return [];
        }
        if ($withDependencies) {
            ['order' => $order, 'report' => $report] = $resolver->activationPlan($id);
            if (! $report->ok()) {
                throw new PluginException($report->message());
            }
        } else {
            $report = $resolver->activationReport($id);
            if (! $report->ok()) {
                $hint = $report->problemsOfKind(ActivationReport::INACTIVE) ? ' Use --with-dependencies to switch them on too.' : '';
                throw new PluginException($report->message() . $hint);
            }
            $order = [$id];
        }
        foreach ($order as $pid) {
            $this->warnings = array_merge($this->warnings, $this->switchOn($pid));
        }
        $this->rebuildCache();

        return $order;
    }

    /**
     * @param bool $cascade also switch off the active plugins that require it (dependents first)
     * @return list<string> ids switched off
     */
    public function deactivate(string $id, bool $cascade = false): array
    {
        $this->manifest($id);
        $resolver = $this->resolver();
        if (! $resolver->isActive($id)) {
            return [];
        }
        $blockers = $resolver->activeDependents($id);
        if ($blockers && ! $cascade) {
            throw new PluginException("{$id} cannot be deactivated: it is required by active " . implode(', ', $blockers) . '. Deactivate those first or use --cascade.');
        }
        $order = $resolver->deactivationCascade($id);
        foreach ($order as $pid) {
            $m = $this->manifest($pid);
            foreach ($m->declares as $type) {
                $this->declarations->get($type)?->setActive($m, false, $this->ledger->codes($pid, $type));
            }
            $this->states->save($this->states->all()[$pid]->withActive(false));
            $this->states->event($pid, 'deactivated');
        }
        $this->rebuildCache();

        return $order;
    }

    /** @param bool $purge also roll the plugin's migrations back (deletes its tables and data) */
    public function uninstall(string $id, bool $purge = false): void
    {
        $m = $this->manifest($id);
        $resolver = $this->resolver();
        if (! $resolver->isInstalled($id)) {
            throw new PluginException("{$id} is not installed.");
        }
        if ($resolver->isActive($id)) {
            throw new PluginException("{$id} is active. Deactivate it before uninstalling.");
        }
        if ($users = $resolver->installedDependents($id)) {
            throw new PluginException("{$id} cannot be uninstalled: installed plugins still require it (" . implode(', ', $users) . ').');
        }
        foreach ($m->declares as $type) {
            $this->declarations->get($type)?->remove($m, $this->ledger->codes($id, $type));
        }
        if ($purge) {
            $this->migrations->rollback($m);
        }
        $this->ledger->clear($id);
        $this->states->forget($id);
        $this->states->event($id, $purge ? 'uninstalled+purged' : 'uninstalled');
        $this->rebuildCache();
    }

    /** Write the boot cache from the active plugins, dependencies first. */
    public function rebuildCache(): void
    {
        $active = array_keys(array_filter($this->states->all(), fn (PluginState $s) => $s->active));
        $active = array_values(array_filter($active, fn ($id) => isset($this->manifests()[$id])));
        $entries = [];
        foreach ($this->resolver()->order($active) as $id) {
            $m = $this->manifests()[$id];
            $entries[] = ['id' => $id, 'provider' => $m->provider, 'path' => $m->path];
        }
        $this->cache->write($this->cacheFile, $entries);
    }

    /** @return list<string> the plugin and every plugin it requires */
    private function closure(string $id): array
    {
        $seen = [];
        $stack = [$id];
        while ($stack) {
            $cur = array_pop($stack);
            if (isset($seen[$cur])) {
                continue;
            }
            $seen[$cur] = true;
            foreach (array_keys($this->manifest($cur)->requires) as $dep) {
                if (isset($this->provided[$dep])) {
                    continue;
                }
                if (! isset($this->manifests()[$dep])) {
                    throw new PluginException("{$cur} requires {$dep}, which is not installed on this system.");
                }
                $stack[] = $dep;
            }
        }

        return array_keys($seen);
    }

    private function installOne(string $id): void
    {
        $m = $this->manifest($id);
        $this->migrations->run($m);
        $this->states->save(new PluginState($id, $m->version, false, gmdate('c'), null));
        $this->states->event($id, 'installed', $m->version);
    }

    /** @return list<string> warnings */
    private function switchOn(string $id): array
    {
        $m = $this->manifest($id);
        if (! $this->resolver()->isInstalled($id)) {
            $this->installOne($id);
        } else {
            $this->migrations->run($m);   // picks up migrations added by an update
        }
        $warnings = [];
        foreach ($m->declares as $type) {
            $file = $m->path . '/resources/' . $type . '.php';
            $handler = $this->declarations->get($type);
            if (! is_file($file)) {
                $warnings[] = "{$id}: declares \"{$type}\" but resources/{$type}.php is missing.";
                continue;
            }
            if ($handler === null) {
                $warnings[] = "{$id}: no handler for \"{$type}\"; install and activate the plugin that manages it (normally Lasabro.Core).";
                continue;
            }
            $items = require $file;
            $codes = $handler->sync($m, is_array($items) ? array_values($items) : []);
            $this->ledger->record($id, $type, $codes);
            $handler->setActive($m, true, $codes);
        }
        $state = $this->states->all()[$id];
        $this->states->save(new PluginState($id, $m->version, true, $state->installedAt, gmdate('c')));
        $this->states->event($id, 'activated');

        return $warnings;
    }
}
