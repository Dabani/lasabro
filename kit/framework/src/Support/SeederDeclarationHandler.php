<?php

namespace Lasabro\Framework\Support;

use Illuminate\Support\Facades\Artisan;
use Lasabro\Framework\Contracts\DeclarationHandler;
use Lasabro\Framework\Contracts\ResourceLedger;
use Lasabro\Framework\Plugin\Manifest;
use Lasabro\Framework\Plugin\PluginException;

/**
 * Declaration type `seeders`: resources/seeders.php returns the seeder classes a plugin needs (lookups, permissions, menu,
 * approval flows...). They are run once, on the plugin's first activation, in the order listed; seeders are idempotent by convention.
 *
 * Adoption: when a plugin is activated on an installation that already holds its data (an upgrade from a version that predates
 * the plugin) the seeders must NOT run again, or default role grants an administrator removed would come back. `lasabro:plugin:activate --adopt`
 * switches the handler to "record only".
 */
final class SeederDeclarationHandler implements DeclarationHandler
{
    /** @var callable(string):void */
    private $runner;

    /** @param callable(string):void|null $runner runs one seeder class; defaults to `db:seed --class` */
    public function __construct(private readonly ResourceLedger $ledger, private bool $adopt = false, ?callable $runner = null)
    {
        $this->runner = $runner ?? static function (string $class): void {
            $code = Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
            if ($code !== 0) {
                throw new PluginException("Seeder {$class} failed:\n" . trim(Artisan::output()));
            }
        };
    }

    public function adopt(bool $adopt = true): void
    {
        $this->adopt = $adopt;
    }

    public function type(): string
    {
        return 'seeders';
    }

    public function sync(Manifest $plugin, array $items): array
    {
        $done = $this->ledger->codes($plugin->id, 'seeders');
        foreach ($items as $class) {
            $class = (string) $class;
            if (in_array($class, $done, true)) {
                continue;                                   // already applied by an earlier activation
            }
            if (! $this->adopt) {
                ($this->runner)($class);
            }
        }

        return array_values(array_unique([...$done, ...array_map('strval', $items)]));
    }

    public function setActive(Manifest $plugin, bool $active, array $codes): void
    {
    }

    public function remove(Manifest $plugin, array $codes): void
    {
    }
}
