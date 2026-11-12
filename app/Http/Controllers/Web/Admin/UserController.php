<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RoleName;
use App\Exceptions\LastActiveSuperadminException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreUserRequest;
use App\Http\Requests\Web\UpdateUserRequest;
use App\Models\Mairie;
use App\Models\User;
use App\Services\Permissions\PermissionManager;
use App\Services\Users\UserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Écran Utilisateurs (W5) : liste (User::visibleTo), création par UserProvisioningService::create (mot de passe
 * temporaire affiché une seule fois), modification, activer/désactiver, réinitialiser le mot de passe, révoquer
 * accès, suppression (soft). Changement d'institution réservé à l'admin national et au superadmin (D9).
 */
class UserController extends Controller
{
    public function index(Request $request, PermissionManager $permissions): View
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();

        return view('users.index', [
            // Rôle modifiable seulement si l'auteur peut gérer le rôle actuel du compte (plafond de rôle).
            'users' => User::with(['roles', 'mairie'])->visibleTo($actor)->orderBy('name')->get(),
            'assignableRoles' => $this->assignableRoles($actor),
            'mairies' => Mairie::active()->orderBy('name')->get(['id', 'name']),
            'canChangeInstitution' => $this->canChangeInstitution($actor),
            // Droits que l'auteur peut déléguer à chaque compte (ex. le maire : gérer les tarifs, suivre la caisse).
            'delegable' => fn (User $user) => $actor->can('update', $user) ? $permissions->delegableFor($actor, $user) : collect(),
        ]);
    }

    public function store(StoreUserRequest $request, UserProvisioningService $provisioning): RedirectResponse
    {
        $role = RoleName::tryFrom((string) $request->input('role'));

        if ($role === null || ! in_array($role, $this->assignableRoles($request->user()), true)) {
            throw ValidationException::withMessages(['role' => 'Rôle invalide.']);
        }

        $result = $provisioning->create(
            $request->user(),
            $request->only(['name', 'phone', 'mairie_id']),
            $role
        );

        return redirect()->route('users.index')
            ->with('status', "Utilisateur « {$result['user']->name} » créé.")
            ->with('temporary_password', $result['temporary_password'])
            ->with('temporary_password_for', $result['user']->name);
    }

    public function update(UpdateUserRequest $request, User $user, UserProvisioningService $provisioning, PermissionManager $permissions): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->only(['name', 'phone']);

        if ($this->canChangeInstitution($actor)) {
            $data += $request->only(['mairie_id']);
        }

        $wasActive = $user->is_active;
        if ($request->has('is_active') && Gate::allows('activate', $user)) {
            $data['is_active'] = $request->boolean('is_active');
        }

        // Changement de rôle (fenêtre de modification) : limité aux rôles que l'auteur peut attribuer.
        $role = RoleName::tryFrom((string) $request->input('role'));
        if ($role !== null && $role !== $user->roleName()) {
            if (! in_array($role, $this->assignableRoles($actor), true)) {
                throw ValidationException::withMessages(['role' => 'Rôle invalide.']);
            }
            $provisioning->changeRole($actor, $user, $role, $this->canChangeInstitution($actor) && $request->filled('mairie_id') ? $request->integer('mairie_id') : null);
            $user->refresh();
            unset($data['mairie_id']);
        }

        try {
            $provisioning->updateByAdmin($actor, $user, $data);
        } catch (LastActiveSuperadminException $e) {
            return redirect()->route('users.index')->with('error', $e->getMessage());
        }

        // Droits délégués (cases de la fenêtre de modification), seulement si la section était affichée.
        if ($request->boolean('delegations_form')) {
            $permissions->delegate($actor, $user->fresh(), array_values(array_filter((array) $request->input('delegations', []), 'is_string')));
        }

        if ($wasActive && ! $user->fresh()->is_active) {
            $provisioning->revokeAccess($user);
        }

        return redirect()->route('users.index')->with('status', "Utilisateur « {$user->name} » modifié.");
    }

    public function destroy(User $user, UserProvisioningService $provisioning): RedirectResponse
    {
        Gate::authorize('delete', $user);

        try {
            $user->delete();
        } catch (LastActiveSuperadminException $e) {
            return redirect()->route('users.index')->with('error', $e->getMessage());
        }

        $provisioning->revokeAccess($user);

        return redirect()->route('users.index')->with('status', "Utilisateur « {$user->name} » supprimé.");
    }

    public function resetPassword(User $user, UserProvisioningService $provisioning): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $temporary = $provisioning->resetPassword(request()->user(), $user);

        return redirect()->route('users.index')
            ->with('status', "Mot de passe réinitialisé pour {$user->name}.")
            ->with('temporary_password', $temporary)
            ->with('temporary_password_for', $user->name);
    }

    public function revokeAccess(User $user, UserProvisioningService $provisioning): RedirectResponse
    {
        Gate::authorize('revokeAccess', $user);

        $provisioning->revokeAccess($user);

        return redirect()->route('users.index')->with('status', "Accès révoqué pour {$user->name}.");
    }

    /**
     * Rôles proposables à la création (plafond de rôle), en écartant le superadmin (créé automatiquement depuis
     * .env) même quand le plafond de l'acteur l'inclurait techniquement.
     *
     * @return list<RoleName>
     */
    private function assignableRoles(User $actor): array
    {
        return array_values(array_filter(
            $actor->roleName()?->manages() ?? [],
            fn (RoleName $role) => $role !== RoleName::Superadmin
        ));
    }

    private function canChangeInstitution(User $actor): bool
    {
        return in_array($actor->roleName(), [RoleName::AdminNational, RoleName::Superadmin], true);
    }
}
