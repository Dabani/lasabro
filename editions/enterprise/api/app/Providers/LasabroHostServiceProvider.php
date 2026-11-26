<?php

namespace App\Providers;

use App\Support\Lasabro\MenuVisibilityHandler;
use App\Support\Lasabro\WidgetVisibilityHandler;
use Illuminate\Support\ServiceProvider;
use Lasabro\Framework\Plugin\DeclarationRegistry;

/**
 * Connects this edition to the LASABRO kit while platform modules are still being extracted from the application.
 *
 *  - PROVIDED lists the capabilities the application itself implements. Plugins may require them; they count as installed
 *    and active. Delete an entry when its plugin is extracted (and the plugin then appears in `lasabro:plugin:list`).
 *  - The handlers apply the `menu` and `widgets` declarations of plugins to this application's tables.
 */
class LasabroHostServiceProvider extends ServiceProvider
{
    private const PROVIDED = [
        'Lasabro.Core' => '1.0.0',
        'Lasabro.Approvals' => '1.0.0',
        'Lasabro.Rules' => '1.0.0',
        'Lasabro.Files' => '1.0.0',
        'Lasabro.Hr' => '1.0.0',
        'Lasabro.Attendance' => '1.0.0',  // Hr, Attendance, Recruitment, Payroll: extracted in a later bundle
    ];

    public function register(): void
    {
        config(['lasabro.provided' => array_merge(config('lasabro.provided', []), self::PROVIDED)]);

        $this->app->afterResolving(DeclarationRegistry::class, function (DeclarationRegistry $registry) {
            $registry->register(new MenuVisibilityHandler());
            $registry->register(new WidgetVisibilityHandler());
        });
    }
}
