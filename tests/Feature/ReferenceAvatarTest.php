<?php

namespace Tests\Feature;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReferenceAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_is_stored_as_512px_square(): void
    {
        Storage::fake('local');
        Profile::create(['handle' => 'viram', 'display_name' => 'viram']);

        $src = tempnam(sys_get_temp_dir(), 'ref').'.png';
        imagepng(imagecreatetruecolor(800, 1000), $src);

        $this->artisan('avatar:reference', ['handle' => 'viram', 'image' => $src])->assertSuccessful();

        $img = imagecreatefromstring(Storage::disk('local')->get('references/viram/default.png'));
        $this->assertSame([512, 512], [imagesx($img), imagesy($img)]);
    }

    public function test_style_option_stores_per_hairstyle_and_rejects_unknown(): void
    {
        Storage::fake('local');
        Profile::create(['handle' => 'viram', 'display_name' => 'viram']);
        $src = tempnam(sys_get_temp_dir(), 'ref').'.png';
        imagepng(imagecreatetruecolor(64, 64), $src);

        $this->artisan('avatar:reference', ['handle' => 'viram', 'image' => $src, '--style' => 'ponytail'])->assertSuccessful();
        Storage::disk('local')->assertExists('references/viram/ponytail.png');

        $this->artisan('avatar:reference', ['handle' => 'viram', 'image' => $src, '--style' => 'mohawk'])->assertFailed();
    }

    public function test_unknown_handle_fails(): void
    {
        $this->artisan('avatar:reference', ['handle' => 'nobody', 'image' => '/nope.png'])->assertFailed();
    }
}
