<?php

namespace App\Support\Lasabro;

/** Declaration type `menu`: resources/menu.php lists the menu item codes a plugin owns. */
final class MenuVisibilityHandler extends CodeVisibilityHandler
{
    public function type(): string
    {
        return 'menu';
    }

    protected function table(): string
    {
        return 'menu_items';
    }
}
