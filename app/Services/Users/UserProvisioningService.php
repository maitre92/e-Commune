<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Models\Mairie;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\PhoneNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Logique partagée de création et de sécurité des comptes.
 * Applique la règle rôle ↔ institution (que la base ne peut pas garantir, cf. CLAUDE.md §4.1)
 * et le plafond de rôle. Un compte n'a jamais deux rôles ni deux institutions.
 */
class UserProvisioningService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Crée un compte. Sans mot de passe fourni, un mot de passe temporaire est généré (il n'est retourné qu'ici)
     * et le compte devra le changer à la première connexion (D14).
     *
     * @param  array{name: string, phone: string, password?: ?string, mairie_id?: ?int, is_active?: bool}  $data
     * @return array{user: User, temporary_password: ?string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(?User $actor, array $data, RoleName $role): array
    {
        $this->assertActorMayCreate($actor, $role);

        // Le maire ne crée des agents que pour sa propre mairie, quoi qu'il soumette.
        if ($actor?->roleName() === RoleName::Maire) {
            $data['mairie_id'] = $actor->mairie_id;
        }

        // D26 : le numéro est l'identifiant de connexion ; il est normalisé en E.164 avant validation et unicité.
        $data['phone'] = PhoneNumber::normalize($data['phone'] ?? null) ?? ($data['phone'] ?? null);

        $data = $this->validated($data, $role);

        $temporary = null;
        if (empty($data['password'])) {
            $temporary = $data['password'] = $this->generateTemporaryPassword();
            $data['must_change_password'] = true;
        }

        $user = DB::transaction(function () use ($actor, $data, $role) {
            $user = User::create($data);
            $user->syncRoles([$role->value]);

            $this->logger->log(
                'users.role_assigned',
                'users',
                "Rôle attribué — {$user->name} : {$role->label()}",
                $user,
                new: ['role' => $role->value],
                actor: $actor,
            );

            return $user;
        });

        return ['user' => $user, 'temporary_password' => $temporary];
    }

    /**
     * Modification par l'utilisateur de ses propres informations (nom, numéro). Rien d'autre : rôle, institution et
     * statut ne passent jamais par ici. L'audit (`users.updated`, anciennes et nouvelles valeurs) est écrit par le modèle.
     *
     * @param  array{name: string, phone: string}  $data
     * @return bool vrai si quelque chose a changé
     */
    public function updateProfile(User $user, array $data): bool
    {
        $user->fill(['name' => $data['name'], 'phone' => $data['phone']]);

        if (! $user->isDirty()) {
            return false;
        }

        $user->save();

        return true;
    }

    /**
     * Photo de profil de l'utilisateur lui-même (JPEG, PNG ou WebP déjà validé) : remplace l'ancienne. Journalisé
     * par le modèle (users.updated).
     */
    public function updatePhoto(User $user, UploadedFile $photo): void
    {
        $old = $user->photo_path;
        $user->forceFill(['photo_path' => $photo->store('users/photos', 'public')])->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }

    /** Retire la photo : les initiales reviennent. */
    public function removePhoto(User $user): void
    {
        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $user->forceFill(['photo_path' => null])->save();
        }
    }

    /**
     * Modification d'un compte par un supérieur (W5, D9, plafond de rôle) : nom et numéro toujours ; institution et
     * statut seulement si le contrôleur les inclut dans $data (institution réservée à l'admin national et au
     * superadmin, statut réservé à `users.activate` — ce service ne fait qu'appliquer et valider ce qu'il reçoit).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateByAdmin(User $actor, User $target, array $data): void
    {
        if (! $actor->isSuperadmin() && ! ($actor->roleName()?->canManage($target->roleName() ?? RoleName::Superadmin) ?? false)) {
            throw new AuthorizationException('Vous ne pouvez pas modifier ce compte.');
        }

        $institution = $target->roleName()?->institution();

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN, Rule::unique('users', 'phone')->ignore($target->getKey())],
        ];

        if (array_key_exists('mairie_id', $data)) {
            $rules['mairie_id'] = [$institution === 'mairie' ? 'required' : 'prohibited', 'nullable', 'integer', Rule::exists('mairies', 'id')->whereNull('deleted_at')->where('is_active', true)];
        }

        if (array_key_exists('is_active', $data)) {
            $rules['is_active'] = ['boolean'];
        }

        $data['phone'] = PhoneNumber::normalize($data['phone'] ?? null) ?? ($data['phone'] ?? null);

        $validated = Validator::make($data, $rules, [
            'phone.regex' => 'Le numéro de téléphone n\'est pas valide (exemple : 70 00 00 01).',
            'mairie_id.prohibited' => 'Ce rôle n\'est pas rattaché à une mairie.',
            'mairie_id.required' => 'Ce rôle exige une mairie.',
        ])->validate();

        $target->update($validated);
    }

    /**
     * Change le rôle d'un compte (fenêtre de modification). L'auteur doit pouvoir gérer l'ancien ET le nouveau rôle
     * (plafond de rôle) ; un maire ne change que les agents de sa mairie. Rôle de mairie : le compte garde sa mairie
     * (ou celle de l'auteur s'il n'en a pas) ; rôle national : plus de mairie. Les accès sont coupés pour que les
     * nouveaux droits s'appliquent tout de suite (Web et mobile).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function changeRole(User $actor, User $target, RoleName $role, ?int $mairieId = null): void
    {
        $current = $target->roleName();
        if ($current === $role) {
            return;
        }

        if (! $actor->isSuperadmin() && (! ($actor->roleName()?->canManage($current ?? RoleName::Superadmin) ?? false) || ! ($actor->roleName()?->canManage($role) ?? false))) {
            throw new AuthorizationException('Vous ne pouvez pas attribuer ce rôle.');
        }
        if ($role === RoleName::Superadmin) {
            throw ValidationException::withMessages(['role' => 'Le rôle superadmin ne s\'attribue pas ici.']);
        }
        if ($actor->roleName() === RoleName::Maire && $target->mairie_id !== $actor->mairie_id) {
            throw new AuthorizationException('Ce compte n\'appartient pas à votre mairie.');
        }

        $mairie = $role->institution() === 'mairie' ? ($mairieId ?? $target->mairie_id ?? $actor->mairie_id) : null;
        if ($role->institution() === 'mairie' && ! Mairie::whereKey($mairie)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['role' => 'Choisissez la mairie de ce compte.']);
        }

        DB::transaction(function () use ($actor, $target, $role, $current, $mairie) {
            if ($target->mairie_id !== $mairie) {
                $target->update(['mairie_id' => $mairie]);
            }
            $target->syncRoles([$role->value]);

            $this->logger->log(
                'users.role_assigned',
                'users',
                "Rôle attribué — {$target->name} : {$role->label()}",
                $target,
                old: ['role' => $current?->value],
                new: ['role' => $role->value],
                actor: $actor,
            );
        });

        $this->revokeAccess($target);
    }

    /**
     * Changement de mot de passe par l'utilisateur lui-même : lève l'obligation de changement, coupe les autres
     * sessions.
     */
    public function changePassword(User $user, string $newPassword, ?string $currentSessionId = null): void
    {
        DB::transaction(function () use ($user, $newPassword, $currentSessionId) {
            $user->forceFill([
                'password' => $newPassword,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();

            $this->revokeAccess($user, $currentSessionId);

            $this->logger->log('auth.password_changed', 'auth', "Mot de passe modifié — {$user->name}", $user, actor: $user);
        });
    }

    /**
     * Réinitialisation par un supérieur : nouveau mot de passe temporaire, changement obligatoire, accès coupés.
     *
     * @return string le mot de passe temporaire (affiché une seule fois à l'auteur)
     */
    public function resetPassword(User $actor, User $target): string
    {
        if (! $actor->isSuperadmin() && ! ($actor->roleName()?->canManage($target->roleName() ?? RoleName::Superadmin) ?? false)) {
            throw new AuthorizationException('Vous ne pouvez pas réinitialiser ce compte.');
        }

        $temporary = $this->generateTemporaryPassword();

        DB::transaction(function () use ($actor, $target, $temporary) {
            $target->forceFill(['password' => $temporary, 'must_change_password' => true, 'password_changed_at' => null])->save();
            $this->revokeAccess($target);
            $this->logger->log('auth.password_reset', 'auth', "Mot de passe réinitialisé — {$target->name}", $target, actor: $actor);
        });

        return $temporary;
    }

    /**
     * Supprime les sessions Web (sauf éventuellement la courante) et révoque tous les jetons de l'application mobile.
     */
    public function revokeAccess(User $user, ?string $exceptSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();

        $user->tokens()->delete();
    }

    /**
     * Mot de passe temporaire conforme à D14 : 12 caractères, lettres et chiffres, sans caractères ambigus.
     */
    public function generateTemporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $digits = '23456789';
        $pool = $letters.$digits;

        $chars = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];
        for ($i = count($chars); $i < 12; $i++) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        return Str::of(implode('', collect($chars)->shuffle()->all()))->toString();
    }

    private function assertActorMayCreate(?User $actor, RoleName $role): void
    {
        // Sans acteur : console (commande Artisan de création du superadmin).
        if ($actor === null || $actor->isSuperadmin()) {
            return;
        }

        if (! ($actor->roleName()?->canManage($role) ?? false)) {
            throw new AuthorizationException('Votre rôle ne permet pas de créer ce type de compte.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(array $data, RoleName $role): array
    {
        $institution = $role->institution();

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            // Identifiant de connexion (D26) : obligatoire, E.164, unique y compris parmi les comptes supprimés.
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN, Rule::unique('users', 'phone')],
            'password' => ['nullable', 'string', Password::defaults()],
            'mairie_id' => [$institution === 'mairie' ? 'required' : 'prohibited', 'nullable', 'integer', Rule::exists('mairies', 'id')->whereNull('deleted_at')],
        ], [
            'phone.regex' => 'Le numéro de téléphone n\'est pas valide (exemple : 70 00 00 01).',
            'mairie_id.prohibited' => 'Ce rôle n\'est pas rattaché à une mairie.',
        ]);

        $validated = $validator->validate();

        // Une institution désactivée n'accueille pas de nouveau compte.
        if ($institution === 'mairie' && ! Mairie::whereKey($validated['mairie_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['mairie_id' => 'Cette mairie est désactivée.']);
        }

        return array_merge($validated, array_filter(['is_active' => $data['is_active'] ?? true, 'must_change_password' => $data['must_change_password'] ?? false], fn ($v) => $v !== null));
    }
}
