<?php

namespace Tests\Feature;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OotdTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): Profile
    {
        return Profile::create([
            'handle' => 'viram',
            'display_name' => 'viram',
            'base_look' => '검은색 단발머리, 갈색 눈',
        ]);
    }

    public function test_root_redirects_to_calendar(): void
    {
        $this->profile();

        $this->get('/')->assertRedirect('/@viram');
    }

    public function test_calendar_shows_current_month(): void
    {
        $this->profile();

        $this->get('/@viram')
            ->assertOk()
            ->assertSee(now()->format('Y년 n월'));
    }

    public function test_outfit_creation_generates_placeholder_avatar_without_api_key(): void
    {
        Storage::fake('public');
        config(['services.gemini.key' => null]);
        $profile = $this->profile();

        $response = $this->post('/days/'.now()->toDateString(), [
            'description' => '오늘은 회색 티셔츠에 하얀색 바지, 검은색 크록스!',
        ]);

        $response->assertRedirect();

        $outfit = $profile->outfits()->first();
        $this->assertNotNull($outfit);
        $this->assertSame('placeholder', $outfit->engine);
        Storage::disk('public')->assertExists($outfit->avatar_path);

        $this->get('/@viram')->assertSee($outfit->avatar_path);
    }

    public function test_outfit_can_be_deleted(): void
    {
        config(['services.gemini.key' => null]);
        Storage::fake('public');
        $profile = $this->profile();
        $date = now()->toDateString();

        $this->post('/days/'.$date, ['description' => '파란 후드']);
        $this->delete('/days/'.$date)->assertRedirect();

        $this->assertSame(0, $profile->outfits()->count());
    }

    public function test_invalid_date_returns_404(): void
    {
        $this->profile();

        $this->get('/days/2026-13-40')->assertNotFound();
    }
}
