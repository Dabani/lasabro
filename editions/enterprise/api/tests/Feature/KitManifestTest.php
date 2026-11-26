<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** GET /api/v1/kit/manifest: the language-neutral description of the installation used by every frontend. */
class KitManifestTest extends TestCase
{
    use CopsTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootCops();
    }

    public function test_a_visitor_gets_edition_and_theme_but_no_plugin_list(): void
    {
        $this->getJson('/api/v1/kit/manifest')
            ->assertOk()
            ->assertJsonPath('data.authenticated', false)
            ->assertJsonPath('data.plugins', [])
            ->assertJsonPath('data.edition.id', config('lasabro.edition.id'))
            ->assertJsonPath('data.theme.preset', 'lasabro');
    }

    public function test_a_signed_in_user_is_recognised(): void
    {
        Sanctum::actingAs($this->makeUser('team_leader'));
        $this->getJson('/api/v1/kit/manifest')->assertOk()->assertJsonPath('data.authenticated', true);
    }

    public function test_appearance_settings_choose_the_preset_and_override_tokens(): void
    {
        $org = Organization::query()->orderBy('created_at')->first();
        SettingsService::set($org->id, 'appearance.preset', 'cops-classic');
        SettingsService::set($org->id, 'appearance.primary', '#112233');
        SettingsService::set($org->id, 'appearance.navbar_width', 300);

        $this->getJson('/api/v1/kit/manifest')
            ->assertOk()
            ->assertJsonPath('data.theme.preset', 'cops-classic')
            ->assertJsonPath('data.theme.overrides.primary', '#112233')
            ->assertJsonPath('data.theme.overrides.navbarWidth', 300)
            ->assertJsonMissingPath('data.theme.overrides.secondary');
    }
}
