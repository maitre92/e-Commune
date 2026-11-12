<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation d'un utilisateur pour l'application mobile.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->roleName();
        $institution = $this->institution();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'photo_url' => $this->photoUrl(),
            'phone' => $this->phone,
            'role' => $role ? ['name' => $role->value, 'label' => $role->label()] : null,
            'institution' => $institution ? [
                'type' => 'mairie',
                'id' => $institution->getKey(),
                'name' => $institution->name,
            ] : null,
            'must_change_password' => $this->must_change_password,
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }
}
