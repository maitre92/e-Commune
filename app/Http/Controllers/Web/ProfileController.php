<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ChangePasswordRequest;
use App\Http\Requests\Web\UpdateProfileRequest;
use App\Services\Users\UserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('roles');

        return view('profile.show', ['user' => $user, 'institution' => $user->institution()]);
    }

    public function update(UpdateProfileRequest $request, UserProvisioningService $users): RedirectResponse
    {
        $changed = $users->updateProfile($request->user(), $request->validated());

        return redirect()->route('profile.show')
            ->with('status', $changed ? 'Vos informations ont été mises à jour.' : 'Aucune modification.')
            ->with('tab', 'info');
    }

    /** Photo de profil (facultative). */
    public function updatePhoto(Request $request, UserProvisioningService $users): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072']], [
            'photo.required' => 'Choisissez une photo.',
            'photo.image' => 'Le fichier doit être une photo (JPEG, PNG ou WebP).',
            'photo.max' => 'La photo ne doit pas dépasser 3 Mo.',
        ]);

        $users->updatePhoto($request->user(), $request->file('photo'));

        return redirect()->route('profile.show')->with('status', 'Votre photo a été mise à jour.')->with('tab', 'info');
    }

    public function deletePhoto(Request $request, UserProvisioningService $users): RedirectResponse
    {
        $users->removePhoto($request->user());

        return redirect()->route('profile.show')->with('status', 'Votre photo a été retirée.')->with('tab', 'info');
    }

    public function updatePassword(ChangePasswordRequest $request, UserProvisioningService $users): RedirectResponse
    {
        $users->changePassword($request->user(), $request->validated('password'), $request->session()->getId());

        return redirect()->route('profile.show')->with('status', 'Mot de passe modifié avec succès.')->with('tab', 'password');
    }
}
