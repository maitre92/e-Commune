<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/** Chaque utilisateur modifie ses propres informations et sa photo de profil, sur le Web et sur le mobile. */
class OwnProfileTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        Storage::fake('public');
    }

    public function test_a_user_adds_changes_and_removes_his_photo_on_the_web(): void
    {
        $user = User::factory()->mairie()->create();

        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('moi.jpg', 300, 300)])->assertSessionHas('status');
        $first = $user->fresh()->photo_path;
        Storage::disk('public')->assertExists($first);
        $this->actingAs($user)->get('/profile')->assertSee($user->fresh()->photoUrl(), false)->assertSee('Changer ma photo');

        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('moi2.png', 300, 300)]);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($user)->delete('/profile/photo');
        $this->assertNull($user->fresh()->photo_path);

        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
    }

    public function test_a_user_edits_his_information_and_photo_from_the_phone(): void
    {
        $user = User::factory()->mairie()->create(['name' => 'Ancien Nom', 'password' => 'Secret-Mobile-9']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', ['name' => 'Nouveau Nom', 'phone' => $user->phone])->assertOk()->assertJsonPath('user.name', 'Nouveau Nom');

        // Changer de numéro (identifiant de connexion) demande le mot de passe actuel.
        $this->putJson('/api/v1/auth/profile', ['name' => 'Nouveau Nom', 'phone' => '70 11 22 33'])->assertUnprocessable()->assertJsonValidationErrors('phone_password');
        $this->putJson('/api/v1/auth/profile', ['name' => 'Nouveau Nom', 'phone' => '70 11 22 33', 'phone_password' => 'Secret-Mobile-9'])->assertOk()->assertJsonPath('user.phone', '+22370112233');

        $url = $this->postJson('/api/v1/auth/photo', ['photo' => UploadedFile::fake()->image('moi.jpg', 300, 300)])->assertOk()->json('user.photo_url');
        $this->assertNotNull($url);
        $this->deleteJson('/api/v1/auth/photo')->assertOk()->assertJsonPath('user.photo_url', null);
    }
}
