<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The admin panel's profile page (header → Profile) also changes the user's photo. */
class AdminProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_page_saves_a_new_photo(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel('admin');
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $this->actingAs($user);

        $this->get(Filament::getProfileUrl())->assertOk();

        Livewire::test(EditProfile::class)
            ->set('data.avatar_url', UploadedFile::fake()->image('me.png', 100, 100))
            ->call('save')
            ->assertHasNoErrors();

        $path = $user->fresh()->avatar_url;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('avatars/', $path);
        Storage::disk('public')->assertExists($path);
    }
}
