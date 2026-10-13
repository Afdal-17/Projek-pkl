<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['nama' => $request->input('nama', $request->input('name'))]);
        $phoneInput = $request->input('phone', $request->input('nomor_telepon'));

        if (! empty($phoneInput)) {
            $cleanPhone = preg_replace('/[^\d+]/', '', (string) $phoneInput);
            $request->merge(['nomor_telepon' => $cleanPhone]);

            $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'nomor_telepon' => ['required', 'string', 'max:32', 'unique:'.User::class.',nomor_telepon'],
                'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            $user = User::create([
                'nama' => $request->nama,
                'nomor_telepon' => $cleanPhone,
                'email' => $request->filled('email') ? $request->email : null,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'status' => false,
                'email_verified_at' => null,
            ]);

            if ($user->email) {
                return $this->kirimOtpDanArahkan($request, $user);
            }

            return redirect()->route('login')->with('status', 'Akun dengan nomor telepon ' . $cleanPhone . ' berhasil dibuat. Menunggu verifikasi dari admin untuk dapat login.');
        }

        $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'status' => false,
            'email_verified_at' => null,
        ]);

        return $this->kirimOtpDanArahkan($request, $user);
    }

    /**
     * Kirim kode OTP ke email user dan arahkan ke halaman verifikasi OTP.
     */
    private function kirimOtpDanArahkan(Request $request, User $user): RedirectResponse
    {
        try {
            app(OtpService::class)->kirim($user);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('status', 'Akun berhasil dibuat, namun gagal mengirim kode OTP ke email Anda. Silakan hubungi admin untuk verifikasi.');
        }

        $request->session()->put('otp_user_id', $user->id_user);

        return redirect()->route('otp.show');
    }
}
