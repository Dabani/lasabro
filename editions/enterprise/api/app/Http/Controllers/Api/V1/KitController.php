<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Lasabro\Framework\Plugin\PluginManager;

class KitController extends Controller
{
    /** appearance setting key => theme token it overrides (an empty / zero setting means "use the preset"). */
    private const THEME_SETTINGS = [
        'appearance.primary' => 'primary',
        'appearance.secondary' => 'secondary',
        'appearance.font_family' => 'fontFamily',
        'appearance.radius' => 'radius',
        'appearance.layout' => 'layout',
        'appearance.navbar_width' => 'navbarWidth',
        'appearance.header_height' => 'headerHeight',
    ];

    /**
     * Description of this installation for any frontend (React today; Vue, Angular or Flutter later read the same document):
     * edition, theme and, for a signed-in user only, the active plugins. Values are validated again by the client.
     */
    public function manifest(Request $request, PluginManager $plugins)
    {
        $org = Organization::whereHas('status', fn ($q) => $q->where('code', 'active'))->orderBy('created_at')->first();

        $preset = config('lasabro.theme.preset', 'lasabro');
        $overrides = [];
        if ($org) {
            $chosen = SettingsService::get($org->id, 'appearance.preset');
            if (is_string($chosen) && $chosen !== '') {
                $preset = $chosen;
            }
            foreach (self::THEME_SETTINGS as $key => $token) {
                $value = SettingsService::get($org->id, $key);
                if ($value !== '' && $value !== 0 && $value !== null) {
                    $overrides[$token] = $value;
                }
            }
        }

        $signedIn = $request->user('sanctum') !== null;
        $active = [];
        if ($signedIn) {
            foreach ($plugins->status() as $row) {
                if ($row['state'] === 'active') {
                    $active[] = ['id' => $row['id'], 'version' => $row['version'], 'tier' => $row['tier'], 'active' => true];
                }
            }
        }

        return response()->json(['data' => [
            'authenticated' => $signedIn,
            'edition' => ['id' => config('lasabro.edition.id'), 'name' => config('lasabro.edition.name')],
            'plugins' => $active,
            'theme' => ['preset' => $preset, 'overrides' => (object) $overrides],
        ]]);
    }
}
