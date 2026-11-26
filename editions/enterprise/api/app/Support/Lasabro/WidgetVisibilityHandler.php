<?php

namespace App\Support\Lasabro;

/** Declaration type `widgets`: resources/widgets.php lists the dashboard widget codes a plugin owns. */
final class WidgetVisibilityHandler extends CodeVisibilityHandler
{
    public function type(): string
    {
        return 'widgets';
    }

    protected function table(): string
    {
        return 'dashboard_widgets';
    }
}
