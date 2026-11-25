<?php

namespace App\Console\Commands;

use App\Support\Fingerprint\FingerprintCollector;
use App\Support\Fingerprint\FingerprintDiff;
use Illuminate\Console\Command;

class LasabroFingerprint extends Command
{
    protected $signature = 'lasabro:fingerprint
        {--out= : Save the fingerprint to this JSON file}
        {--compare= : Compare the current installation with a saved fingerprint}
        {--strict : Also fail when only the controller serving a route changed}';

    protected $description = 'Capture or compare a fingerprint of routes, permissions, menu, schema and schedule (refactoring safety net).';

    public function handle(FingerprintCollector $collector): int
    {
        $current = $collector->collect();

        if ($out = $this->option('out')) {
            $dir = dirname($out);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($out, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            $this->info("Fingerprint saved to {$out}");
        }

        if ($baselinePath = $this->option('compare')) {
            return $this->compare($baselinePath, $current);
        }

        foreach ($current['sections'] as $section => $lines) {
            $this->line(sprintf('  %-14s %d', $section, count($lines)));
        }

        return self::SUCCESS;
    }

    private function compare(string $path, array $current): int
    {
        if (! is_file($path)) {
            $this->error("Baseline not found: {$path}");

            return self::FAILURE;
        }
        $baseline = json_decode((string) file_get_contents($path), true);
        if (! is_array($baseline)) {
            $this->error("Baseline is not valid JSON: {$path}");

            return self::FAILURE;
        }
        if (($baseline['meta']['driver'] ?? null) !== ($current['meta']['driver'] ?? null)) {
            $this->warn('Baseline and current database drivers differ: the schema section is not comparable.');
        }

        $diff = FingerprintDiff::compare($baseline, $current);
        if ($diff === []) {
            $this->info('Identical: nothing visible to users or integrations has changed.');

            return self::SUCCESS;
        }

        foreach ($diff as $section => $change) {
            $info = in_array($section, FingerprintDiff::INFORMATIONAL, true) ? ' (informational)' : '';
            $this->line("<fg=yellow>== {$section}{$info}</>  -{$this->count($change['removed'])}  +{$this->count($change['added'])}");
            foreach (array_slice($change['removed'], 0, 25) as $line) {
                $this->line("  <fg=red>- {$line}</>");
            }
            foreach (array_slice($change['added'], 0, 25) as $line) {
                $this->line("  <fg=green>+ {$line}</>");
            }
        }

        if (FingerprintDiff::hasBlockingDifferences($diff, (bool) $this->option('strict'))) {
            $this->error('Differences found: review them before continuing.');

            return self::FAILURE;
        }
        $this->info('Only informational differences (code moved, behaviour unchanged).');

        return self::SUCCESS;
    }

    private function count(array $lines): int
    {
        return count($lines);
    }
}
