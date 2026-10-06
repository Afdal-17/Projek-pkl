<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function adminEdit(Request $request): View
    {
        $user = $request->user();

        return view('admin.profile', [
            'user' => $user,
            'avatarUrl' => $user->avatar_path ? asset('storage/' . $user->avatar_path) : null,
        ]);
    }

    public function frontendEdit(Request $request): View
    {
        $user = $request->user();

        return view('account', [
            'user' => $user,
            'avatarUrl' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
        ]);
    }

    public function frontendUpdate(ProfileUpdateRequest $request): RedirectResponse|JsonResponse
    {
        $request->user()->fill($request->validated())->save();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'profile-updated']);
        }

        return Redirect::route('account')->with('status', 'profile-updated');
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048']]);
        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $path]);

        return response()->json(['avatar_url' => asset('storage/'.$path)]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json([], 204);
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
