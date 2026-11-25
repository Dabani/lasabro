<?php

declare(strict_types=1);

namespace Lasabro\Framework\Plugin;

/**
 * What a plugin says about itself. It lives in the plugin's composer.json under `extra.lasabro`:
 *
 *   "extra": { "lasabro": {
 *     "id": "Lasabro.Hr", "title": "Human Resources", "tier": "domain",
 *     "provider": "Lasabro\\Hr\\HrServiceProvider",
 *     "requires": { "Lasabro.Core": "^1.0" },          // hard: activation is refused without them
 *     "suggests": { "Lasabro.Leave": "leave balances in attendance" },   // soft: features that light up when present
 *     "frontends": { "react": "@lasabro/hr-react" },
 *     "declares": ["permissions", "menu", "widgets", "lookups"]          // resources/<type>.php files to sync on activation
 *   }}
 */
final class Manifest
{
    public const TIERS = ['platform', 'domain', 'edition'];

    /**
     * @param array<string,string> $requires  plugin id => version constraint
     * @param array<string,string> $suggests  plugin id => why it helps
     * @param array<string,string> $frontends frontend flavour => npm package
     * @param list<string>         $declares  resource types shipped as resources/<type>.php
     */
    public function __construct(
        public readonly string $id,
        public readonly string $package,
        public readonly string $version,
        public readonly string $title,
        public readonly string $tier,
        public readonly string $provider,
        public readonly array $requires,
        public readonly array $suggests,
        public readonly array $frontends,
        public readonly array $declares,
        public readonly string $path,
    ) {
    }

    /**
     * @param array<string,mixed> $composer decoded composer.json of the plugin
     * @throws PluginException when the manifest is missing or malformed
     */
    public static function fromComposer(array $composer, string $path, ?string $version = null): self
    {
        $package = (string) ($composer['name'] ?? '');
        $m = $composer['extra']['lasabro'] ?? null;
        if (! is_array($m)) {
            throw new PluginException("{$path}: composer.json has no extra.lasabro manifest.");
        }

        $id = (string) ($m['id'] ?? '');
        if (! self::isValidId($id)) {
            throw new PluginException("{$path}: manifest id \"{$id}\" is invalid. Use the form Lasabro.PluginName (letters and digits, each part starting with a capital).");
        }
        $tier = (string) ($m['tier'] ?? 'domain');
        if (! in_array($tier, self::TIERS, true)) {
            throw new PluginException("{$id}: tier \"{$tier}\" is invalid (" . implode(', ', self::TIERS) . ').');
        }
        $provider = (string) ($m['provider'] ?? '');
        if ($provider === '') {
            throw new PluginException("{$id}: manifest has no service provider.");
        }

        $requires = self::stringMap($id, 'requires', $m['requires'] ?? []);
        if (isset($requires[$id])) {
            throw new PluginException("{$id}: a plugin cannot require itself.");
        }
        $declares = array_values(array_map('strval', (array) ($m['declares'] ?? [])));
        foreach ($declares as $type) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $type)) {
                throw new PluginException("{$id}: declared resource type \"{$type}\" is invalid (lower-case letters, digits, underscore).");
            }
        }

        return new self(
            $id,
            $package,
            $version ?? (string) ($composer['version'] ?? 'dev-main'),
            (string) ($m['title'] ?? $id),
            $tier,
            $provider,
            $requires,
            self::stringMap($id, 'suggests', $m['suggests'] ?? []),
            self::stringMap($id, 'frontends', $m['frontends'] ?? []),
            $declares,
            rtrim($path, '/\\'),
        );
    }

    public static function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[A-Z][A-Za-z0-9]*\.[A-Z][A-Za-z0-9]*$/', $id);
    }

    /** "Lasabro.PluginManager" => "plugin-manager": used for folders, npm names and route prefixes. */
    public function slug(): string
    {
        $name = substr($this->id, (int) strpos($this->id, '.') + 1);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }

    /** @return array<string,string> */
    private static function stringMap(string $id, string $key, mixed $value): array
    {
        if (! is_array($value)) {
            throw new PluginException("{$id}: \"{$key}\" must be an object.");
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (! is_string($k) || ! is_string($v)) {
                throw new PluginException("{$id}: \"{$key}\" must map plugin ids to text.");
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
