<?php

namespace App\Support\Fingerprint;

/**
 * Compares two fingerprints (see FingerprintCollector) section by section.
 *
 * A fingerprint is `['sections' => ['routes' => [line, ...], 'permissions' => [line, ...], ...]]`.
 * Every line is a plain string, so the comparison is a set difference: what disappeared, what appeared.
 * This class has no framework dependency on purpose, so it can be unit-tested and reused by the Lasabro kit.
 */
final class FingerprintDiff
{
    /**
     * Sections that are reported but never fail a comparison. They describe *where* code lives
     * (controller class names), which legitimately changes when code moves into plugins.
     */
    public const INFORMATIONAL = ['route_actions'];

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $current
     * @return array<string, array{removed: list<string>, added: list<string>}> only the sections that differ
     */
    public static function compare(array $baseline, array $current): array
    {
        $before = $baseline['sections'] ?? [];
        $after = $current['sections'] ?? [];
        $diff = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $section) {
            $a = array_values(array_unique($before[$section] ?? []));
            $b = array_values(array_unique($after[$section] ?? []));
            $removed = array_values(array_diff($a, $b));
            $added = array_values(array_diff($b, $a));
            if ($removed || $added) {
                sort($removed);
                sort($added);
                $diff[$section] = ['removed' => $removed, 'added' => $added];
            }
        }

        ksort($diff);

        return $diff;
    }

    /**
     * @param  array<string, array{removed: list<string>, added: list<string>}>  $diff
     */
    public static function hasBlockingDifferences(array $diff, bool $strict = false): bool
    {
        foreach (array_keys($diff) as $section) {
            if ($strict || ! in_array($section, self::INFORMATIONAL, true)) {
                return true;
            }
        }

        return false;
    }
}
