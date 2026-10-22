<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Transfer;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

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

    public function adminUpdatePassword(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'password-updated',
                'message' => 'Password berhasil diperbarui.',
            ]);
        }

        return back()->with('status', 'password-updated');
    }

    public function frontendEdit(Request $request): View
    {
        $user = $request->user();

        return view('account', [
            'user' => $user,
            'avatarUrl' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
        ]);
    }

    public function frontendUpdate(ProfileUpdateRequest $request, CurrencyService $currencyService): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $newCurrency = $validated['mata_uang'] ?? null;

        // Mata uang hanya preferensi tampilan; nilai keuangan tetap tersimpan dalam IDR (base)
        if ($newCurrency && $newCurrency !== $user->mata_uang) {
            $currencyService->setUserCurrency($user, $newCurrency);
        }

        unset($validated['mata_uang']);
        $user->fill($validated)->save();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'profile-updated',
                'currency' => $user->mata_uang,
            ]);
        }

        return Redirect::route('account')->with('status', 'profile-updated');
    }

    public function switchCurrency(Request $request, CurrencyService $currencyService): JsonResponse
    {
        $validated = $request->validate([
            'mata_uang' => ['required', 'string', 'in:IDR,USD,EUR'],
        ]);

        $user = $request->user();
        $oldCurrency = $user->mata_uang ?? 'IDR';
        $newCurrency = $validated['mata_uang'];

        if ($oldCurrency !== $newCurrency) {
            $currencyService->setUserCurrency($user, $newCurrency);
        }

        return response()->json([
            'success' => true,
            'old_currency' => $oldCurrency,
            'new_currency' => $newCurrency,
            'message' => "Mata uang berhasil diubah ke {$newCurrency}.",
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048']]);
        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Profile photo could not be written to the public storage disk.');
        }

        $previousPath = $user->avatar_path;
        $user->update(['avatar_path' => $path]);

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

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
     *
     * Dulu error 500: penghapusan user mencascade penghapusan dompet, tetapi
     * baris transfer dari pengguna lain (yang tujuannya menyentuh dompet user)
     * dibatasi foreign key restrictOnDelete. Sekarang semua transfer yang
     * menyentuh dompet user dihapus lebih dulu dalam satu transaksi DB.
     *
     * Konsekuensi yang disengaja: riwayat transfer pihak lain tetap bertahan
     * sebagai baris transaksi dengan id_transfer NULL (FK nullOnDelete), dan
     * saldo pihak lain TIDAK dihitung ulang — uang mereka sudah dipotong saat
     * transfer dilakukan.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Logout TERLEBIH DAHULU, sebelum baris user dihapus:
        // SessionGuard::logout() memutar remember_token lewat $user->save().
        // Jika user sudah terhapus, save() akan melakukan INSERT dan
        // menghidupkan kembali baris user (efek "zombie").
        Auth::logout();

        DB::transaction(function () use ($user): void {
            $dompetIds = $user->dompet()->pluck('id_dompet');

            // Transfer outgoing (milik user) maupun incoming (dari pengguna
            // lain ke dompet user) harus dihapus lebih dulu — FK transfer ke
            // dompet memakai restrictOnDelete.
            Transfer::query()
                ->whereIn('id_dompet_asal', $dompetIds)
                ->orWhereIn('id_dompet_tujuan', $dompetIds)
                ->delete();

            // Dompet, transaksi turunan, kategori, target tabungan, dan
            // notifikasi ikut terhapus otomatis via cascadeOnDelete.
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
