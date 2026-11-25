<?php

declare(strict_types=1);

namespace Lasabro\Framework\Plugin;

use Composer\Semver\Semver;

/**
 * Answers every dependency question without touching the framework or the database, so it is easy to test.
 * `requires` are hard dependencies; `suggests` are only mentioned in notices.
 */
final class DependencyResolver
{
    /**
     * @param array<string,Manifest>    $manifests plugins whose packages are present
     * @param array<string,PluginState> $states    what is installed/active here
     */
    public function __construct(private readonly array $manifests, private readonly array $states)
    {
    }

    public function isActive(string $id): bool
    {
        return isset($this->states[$id]) && $this->states[$id]->active;
    }

    public function isInstalled(string $id): bool
    {
        return isset($this->states[$id]);
    }

    /** Problems that stop $id from being activated right now. */
    public function activationReport(string $id): ActivationReport
    {
        $problems = [];
        foreach ($this->manifest($id)->requires as $dep => $constraint) {
            $p = $this->problemWith($dep, $constraint);
            if ($p !== null) {
                $problems[] = $p;
            }
        }

        return new ActivationReport($id, $problems);
    }

    /**
     * Everything that must be switched on, in the order to do it, so that $id can run.
     * Dependencies that are missing or too old cannot be fixed by activation and are reported as problems.
     *
     * @return array{order:list<string>,report:ActivationReport}
     */
    public function activationPlan(string $id): array
    {
        $order = [];
        $problems = [];
        $this->visit($id, [], $order, $problems);

        return ['order' => $order, 'report' => new ActivationReport($id, $problems)];
    }

    /** Active plugins that directly require $id. @return list<string> */
    public function activeDependents(string $id): array
    {
        $out = [];
        foreach ($this->manifests as $other => $m) {
            if (isset($m->requires[$id]) && $this->isActive($other)) {
                $out[] = $other;
            }
        }
        sort($out);

        return $out;
    }

    /** Installed (active or not) plugins that directly require $id. @return list<string> */
    public function installedDependents(string $id): array
    {
        $out = [];
        foreach ($this->manifests as $other => $m) {
            if (isset($m->requires[$id]) && $this->isInstalled($other)) {
                $out[] = $other;
            }
        }
        sort($out);

        return $out;
    }

    /** All available plugins that directly require $id, active or not. @return list<string> */
    public function dependentsOf(string $id): array
    {
        $out = [];
        foreach ($this->manifests as $other => $m) {
            if (isset($m->requires[$id])) {
                $out[] = $other;
            }
        }
        sort($out);

        return $out;
    }

    /**
     * Plugins to switch off, dependents first, if $id is deactivated together with everything that needs it.
     *
     * @return list<string>
     */
    public function deactivationCascade(string $id): array
    {
        $set = [$id => true];
        $queue = [$id];
        while ($queue) {
            $current = array_shift($queue);
            foreach ($this->activeDependents($current) as $dep) {
                if (! isset($set[$dep])) {
                    $set[$dep] = true;
                    $queue[] = $dep;
                }
            }
        }

        return array_reverse($this->order(array_keys($set)));
    }

    /**
     * Dependency order (dependencies before dependents) of the given plugins; ties broken alphabetically.
     *
     * @param list<string> $ids
     * @return list<string>
     */
    public function order(array $ids): array
    {
        $set = array_fill_keys($ids, true);
        $remaining = [];
        foreach ($ids as $id) {
            $remaining[$id] = isset($this->manifests[$id])
                ? array_values(array_filter(array_keys($this->manifests[$id]->requires), fn ($d) => isset($set[$d])))
                : [];
        }
        ksort($remaining);
        $out = [];
        while ($remaining) {
            $ready = array_keys(array_filter($remaining, fn ($d) => array_diff($d, $out) === []));
            if (! $ready) {
                throw new PluginException('Circular plugin dependency between: ' . implode(', ', array_keys($remaining)) . '.');
            }
            foreach ($ready as $r) {
                $out[] = $r;
                unset($remaining[$r]);
            }
        }

        return $out;
    }

    /** The plain-language dependency notice shown by the CLI, the Plugin Manager and the generated README. */
    public function notice(string $id): string
    {
        $m = $this->manifest($id);
        $lines = [];
        if ($m->requires === []) {
            $lines[] = "{$id} has no required plugins.";
        } else {
            $need = [];
            foreach ($m->requires as $dep => $constraint) {
                $need[] = "{$dep} {$constraint}";
            }
            $lines[] = "{$id} requires " . $this->joinList($need) . '.';
            $report = $this->activationReport($id);
            if (! $report->ok()) {
                $lines[] = 'Right now: ' . substr($report->message(), strlen("{$id} cannot be activated: "));
            }
        }
        if ($m->suggests !== []) {
            $s = [];
            foreach ($m->suggests as $dep => $why) {
                $s[] = $why === '' ? $dep : "{$dep} ({$why})";
            }
            $lines[] = 'Works better with ' . $this->joinList($s) . '.';
        }
        $by = $this->dependentsOf($id);
        if ($by) {
            $lines[] = 'Required by ' . $this->joinList($by) . '.';
        }

        return implode(' ', $lines);
    }

    /** Development checkouts (dev-main, 1.x-dev) satisfy every constraint: the monorepo is always in step with itself. */
    public static function satisfies(string $version, string $constraint): bool
    {
        if (str_starts_with($version, 'dev-') || str_ends_with($version, '-dev')) {
            return true;
        }
        try {
            return Semver::satisfies($version, $constraint);
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    private function manifest(string $id): Manifest
    {
        return $this->manifests[$id] ?? throw new PluginException("Unknown plugin {$id}.");
    }

    /** @return array{plugin:string,kind:string,constraint:string,found:?string}|null */
    private function problemWith(string $dep, string $constraint): ?array
    {
        if (! isset($this->manifests[$dep])) {
            return ['plugin' => $dep, 'kind' => ActivationReport::MISSING, 'constraint' => $constraint, 'found' => null];
        }
        $found = $this->manifests[$dep]->version;
        if (! self::satisfies($found, $constraint)) {
            return ['plugin' => $dep, 'kind' => ActivationReport::VERSION, 'constraint' => $constraint, 'found' => $found];
        }
        if (! $this->isActive($dep)) {
            return ['plugin' => $dep, 'kind' => ActivationReport::INACTIVE, 'constraint' => $constraint, 'found' => $found];
        }

        return null;
    }

    /**
     * @param list<string> $stack
     * @param list<string> $order
     * @param list<array{plugin:string,kind:string,constraint:string,found:?string}> $problems
     */
    private function visit(string $id, array $stack, array &$order, array &$problems): void
    {
        if (in_array($id, $order, true)) {
            return;
        }
        if (in_array($id, $stack, true)) {
            throw new PluginException('Circular plugin dependency: ' . implode(' -> ', [...$stack, $id]) . '.');
        }
        foreach ($this->manifest($id)->requires as $dep => $constraint) {
            $p = $this->problemWith($dep, $constraint);
            if ($p === null) {
                continue;
            }
            if ($p['kind'] === ActivationReport::INACTIVE) {
                $this->visit($dep, [...$stack, $id], $order, $problems);
            } else {
                $problems[] = $p;
            }
        }
        if (! $this->isActive($id)) {
            $order[] = $id;
        }
    }

    /** @param list<string> $items */
    private function joinList(array $items): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }
}
