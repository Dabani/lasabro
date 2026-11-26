<?php

namespace App\Support\Lasabro;

use Illuminate\Support\Facades\DB;
use Lasabro\Framework\Contracts\DeclarationHandler;
use Lasabro\Framework\Plugin\Manifest;

/**
 * Shows or hides the rows of a table that a plugin owns, matched by `code`. A plugin declares the codes it owns in
 * resources/<type>.php; an entry ending in `*` matches every code that starts with the text before it ("procurement.*").
 * The rows themselves are created by the plugin's seeders; deactivating a plugin only flips `is_active`, nothing is deleted.
 */
abstract class CodeVisibilityHandler implements DeclarationHandler
{
    abstract protected function table(): string;

    public function sync(Manifest $plugin, array $items): array
    {
        return array_values(array_map('strval', $items));
    }

    public function setActive(Manifest $plugin, bool $active, array $codes): void
    {
        if ($codes === []) {
            return;
        }
        DB::table($this->table())->where(function ($query) use ($codes) {
            foreach ($codes as $code) {
                if (str_ends_with($code, '*')) {
                    $prefix = strtr(substr($code, 0, -1), ['!' => '!!', '%' => '!%', '_' => '!_']);
                    $query->orWhereRaw("code like ? escape '!'", [$prefix . '%']);
                } else {
                    $query->orWhere('code', $code);
                }
            }
        })->update(['is_active' => $active]);
    }

    public function remove(Manifest $plugin, array $codes): void
    {
        $this->setActive($plugin, false, $codes);   // uninstalling hides; data stay unless the plugin is purged
    }
}
