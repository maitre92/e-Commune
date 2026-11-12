<?php

namespace Tests\Feature\Users;

use App\Enums\RoleName;
use App\Exceptions\LastActiveSuperadminException;
use App\Models\Mairie;
use App\Models\User;
use App\Services\Users\UserProvisioningService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private UserProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->service = app(UserProvisioningService::class);
    }

    public function test_an_agent_mairie_requires_a_mairie(): void
    {
        $mairie = Mairie::factory()->create();
        $superadmin = $this->superadmin();

        ['user' => $user] = $this->service->create($superadmin, ['name' => 'Agent', 'phone' => '+22370000013', 'mairie_id' => $mairie->id], RoleName::Mairie);
        $this->assertSame(RoleName::Mairie, $user->roleName());
        $this->assertSame($mairie->id, $user->mairie_id);

        $this->expectException(ValidationException::class);
        $this->service->create($superadmin, ['name' => 'Agent 2', 'phone' => '+22370000014'], RoleName::Mairie);
    }

    public function test_roles_without_institution_refuse_one(): void
    {
        $mairie = Mairie::factory()->create();
        $superadmin = $this->superadmin();

        foreach ([RoleName::AdminNational, RoleName::Superadmin] as $role) {
            try {
                $this->service->create($superadmin, ['name' => 'N', 'phone' => '+22370000015'.strlen($role->value), 'mairie_id' => $mairie->id], $role);
                $this->fail("$role->value ne doit pas avoir d'institution");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('mairie_id', $e->errors());
            }
        }
    }

    public function test_an_inactive_or_deleted_institution_takes_no_new_account(): void
    {
        $superadmin = $this->superadmin();
        $inactive = Mairie::factory()->inactive()->create();
        $deleted = Mairie::factory()->create();
        $deleted->delete();

        foreach ([$inactive, $deleted] as $i => $mairie) {
            try {
                $this->service->create($superadmin, ['name' => 'P', 'phone' => '+22370000016'.$i, 'mairie_id' => $mairie->id], RoleName::Mairie);
                $this->fail('doit refuser');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('mairie_id', $e->errors());
            }
        }
    }

    public function test_the_role_ceiling_is_enforced(): void
    {
        $mairie = Mairie::factory()->create();
        $admin = User::factory()->adminNational()->create();
        $agent = User::factory()->mairie($mairie)->create();

        // Admin national : agents de mairie oui ; admin national ou superadmin non.
        $this->service->create($admin, ['name' => 'M', 'phone' => '+22370000017', 'mairie_id' => $mairie->id], RoleName::Mairie);
        foreach ([RoleName::AdminNational, RoleName::Superadmin] as $role) {
            try {
                $this->service->create($admin, ['name' => 'Z', 'phone' => '+2237000001'.strlen($role->value)], $role);
                $this->fail('plafond de rôle');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        // Un agent de mairie ne crée aucun compte.
        $this->expectException(AuthorizationException::class);
        $this->service->create($agent, ['name' => 'Autre', 'phone' => '+22370000020', 'mairie_id' => $mairie->id], RoleName::Mairie);
    }

    public function test_without_password_a_compliant_temporary_one_is_generated_and_must_be_changed(): void
    {
        ['user' => $user, 'temporary_password' => $temporary] = $this->service->create($this->superadmin(), ['name' => 'Ad', 'phone' => '+22370000021'], RoleName::AdminNational);

        $this->assertNotNull($temporary);
        $this->assertSame(12, strlen($temporary));
        $this->assertMatchesRegularExpression('/[A-Za-z]/', $temporary);
        $this->assertMatchesRegularExpression('/\d/', $temporary);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($temporary, $user->password));
        $this->assertNotSame($temporary, $user->getRawOriginal('password'));
    }

    public function test_the_password_policy_d14_is_enforced(): void
    {
        foreach (['court1', 'seulement-des-lettres', '1234567890'] as $weak) {
            try {
                $this->service->create($this->superadmin(), ['name' => 'W', 'phone' => '+22370000022'.substr(md5($weak), 0, 6), 'password' => $weak], RoleName::AdminNational);
                $this->fail("« $weak » aurait dû être refusé");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('password', $e->errors());
            }
        }
    }

    public function test_the_phone_is_required_normalized_valid_and_unique_even_after_deletion(): void
    {
        $superadmin = $this->superadmin();
        ['user' => $first] = $this->service->create($superadmin, ['name' => 'A', 'phone' => '70 00 00 12'], RoleName::AdminNational);
        $this->assertSame('+22370000012', $first->phone, 'Le numéro est normalisé en E.164.');

        foreach ([[], ['phone' => ''], ['phone' => 'pas-un-numero'], ['phone' => '123']] as $extra) {
            try {
                $this->service->create($superadmin, ['name' => 'B'] + $extra, RoleName::AdminNational);
                $this->fail('numéro obligatoire et valide');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('phone', $e->errors());
            }
        }

        // Même numéro sous une autre écriture : refusé.
        foreach (['+223 70 00 00 12', '0022370000012', '70000012'] as $again) {
            try {
                $this->service->create($superadmin, ['name' => 'C', 'phone' => $again], RoleName::AdminNational);
                $this->fail('numéro déjà utilisé');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('phone', $e->errors());
            }
        }

        $first->delete(); // soft delete : le numéro reste réservé
        $this->expectException(ValidationException::class);
        $this->service->create($superadmin, ['name' => 'D', 'phone' => '+22370000012'], RoleName::AdminNational);
    }

    public function test_change_password_clears_the_obligation_and_revokes_other_access(): void
    {
        $user = User::factory()->adminNational()->mustChangePassword()->create();
        \DB::table('sessions')->insert([
            ['id' => 'autre', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'courante', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->service->changePassword($user, 'Nouveau-Secret-9', 'courante');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('Nouveau-Secret-9', $user->password));
        $this->assertSame(['courante'], \DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_reset_password_gives_a_temporary_one_and_a_lower_role_cannot_reset_a_higher_one(): void
    {
        $mairie = Mairie::factory()->create();
        $admin = User::factory()->adminNational()->create();
        $agent = User::factory()->mairie($mairie)->create();

        $temporary = $this->service->resetPassword($admin, $agent);
        $this->assertTrue($agent->fresh()->must_change_password);
        $this->assertTrue(Hash::check($temporary, $agent->fresh()->password));

        $this->expectException(AuthorizationException::class);
        $this->service->resetPassword($agent, $admin);
    }

    public function test_the_last_active_superadmin_is_protected(): void
    {
        $only = $this->superadmin();

        try {
            $only->update(['is_active' => false]);
            $this->fail('désactivation refusée');
        } catch (LastActiveSuperadminException) {
            $this->assertTrue($only->fresh()->is_active);
        }

        try {
            $only->delete();
            $this->fail('suppression refusée');
        } catch (LastActiveSuperadminException) {
            $this->assertNull($only->fresh()->deleted_at);
        }

        $second = $this->superadmin();
        $only->update(['is_active' => false]); // désormais autorisé : un autre superadmin actif existe
        $this->assertFalse($only->fresh()->is_active);

        $this->expectException(LastActiveSuperadminException::class);
        $second->delete(); // $second est maintenant le dernier
    }
}
