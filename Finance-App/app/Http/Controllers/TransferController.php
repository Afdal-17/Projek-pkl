<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use App\Models\Transfer;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\NotifikasiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TransferController extends Controller
{
    /**
     * Jendela deteksi kiriman kembar (tap ganda saat loading).
     * Dalam rentang ini, payload identik dari user yang sama hanya diproses sekali.
     */
    private const DUPLICATE_GUARD_SECONDS = 30;

    public function index(): View
    {
        $wallets = Dompet::where('id_user', Auth::id())
            ->orderBy('nama_dompet')
            ->get()
            ->map(fn (Dompet $wallet): array => [
                'id' => $wallet->id_dompet,
                'name' => $wallet->nama_dompet,
                'balance' => (float) $wallet->saldo,
            ]);

        return view('transfer', compact('wallets'));
    }

    public function frontendRecipients(): JsonResponse
    {
        $query = trim((string) request('query'));
        abort_if($query === '', 422, 'Masukkan nama, email, atau ID penerima.');

        $recipient = User::query()
            ->where('status', true)
            ->where('role', 'user')
            ->where(function (Builder $queryBuilder) use ($query): void {
                $queryBuilder->where('email', $query)
                    ->orWhere('nama', $query)
                    ->orWhere('id_user', ctype_digit($query) ? (int) $query : 0);
            })
            ->whereHas('dompet')
            ->with(['dompet' => fn ($wallets) => $wallets->orderBy('nama_dompet')])
            ->first();

        abort_if($recipient === null, 404, 'User tidak ditemukan atau belum memiliki dompet.');

        $wallet = $recipient->dompet->where('terima_transfer', true)->first();

        abort_if($wallet === null, 404, 'Penerima belum mengaktifkan dompet untuk menerima transfer.');

        return response()->json([
            'id_dompet_tujuan' => $wallet->id_dompet,
            'penerima' => $recipient->nama,
            'dompet' => $wallet->nama_dompet,
        ]);
    }

    public function searchRecipients(): JsonResponse
    {
        $query = trim((string) request('query'));

        if ($query === '') {
            return response()->json([]);
        }

        $users = User::query()
            ->where('role', 'user')
            ->whereNotNull('email_verified_at')
            ->where('id_user', '!=', (int) Auth::id())
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('nama', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%')
                    ->orWhere('id_user', ctype_digit($query) ? (int) $query : 0);
            })
            ->with(['dompet' => fn ($wallets) => $wallets->orderBy('nama_dompet')])
            ->limit(8)
            ->get();

        return response()->json(
            $users->map(function (User $user): array {
                $walletsAktif = $user->dompet->where('terima_transfer', true)
                    ->sortBy('nama_dompet')
                    ->values();

                return [
                    'id_user' => $user->id_user,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'is_banned' => $user->isBanned(),
                    'has_wallet' => $walletsAktif->isNotEmpty(),
                    'wallets' => $walletsAktif->map(function (Dompet $wallet): array {
                        return [
                            'id' => $wallet->id_dompet,
                            'name' => $wallet->nama_dompet,
                        ];
                    })->values(),
                    'id_dompet_tujuan' => $walletsAktif->first()?->id_dompet,
                    'id_dompet_tujuan_default' => $walletsAktif->first()?->id_dompet,
                ];
            })->values()
        );
    }

    public function create(): View
    {
        // Hanya dompet milik user ini yang boleh tampil. Transfer ke user lain
        // dipilih lewat pencarian penerima (transfer.blade.php), bukan daftar
        // global yang membocorkan dompet semua user.
        $wallets = Dompet::where('id_user', Auth::id())
            ->orderBy('nama_dompet')
            ->get();

        return view('transfer.create', [
            'dompet' => $wallets,
            'dompetPengirim' => $wallets,
        ]);
    }

    public function store(StoreTransferRequest $request, NotifikasiService $notifikasiService): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) Auth::id();

        /* Tolak kiriman kembar (mis. tap ganda saat loading). Slot dipesan SEBELUM
         * proses, sehingga tap kedua yang datang bersamaan pun ikut terblokir. */
        $dupKey = 'transfer-duplikat:'.$userId.':'.sha1(json_encode([
            $data['id_dompet_asal'],
            $data['id_dompet_tujuan'],
            (float) $data['jumlah'],
            $data['tanggal_transfer'],
            $data['catatan'] ?? null,
        ]));

        if (RateLimiter::tooManyAttempts($dupKey, 1)) {
            throw ValidationException::withMessages([
                'jumlah' => 'Transfer ini baru saja diproses — hanya satu yang tersimpan. '
                    .'Jika memang disengaja, ubah sedikit jumlahnya, '
                    .'atau tunggu '.self::DUPLICATE_GUARD_SECONDS.' detik.',
            ]);
        }

        RateLimiter::hit($dupKey, self::DUPLICATE_GUARD_SECONDS);

        try {
            DB::transaction(function () use ($data, $userId, $notifikasiService): void {
                $wallets = Dompet::whereIn('id_dompet', [$data['id_dompet_asal'], $data['id_dompet_tujuan']])
                    ->orderBy('id_dompet')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id_dompet');
                $source = $wallets->get((int) $data['id_dompet_asal']);
                $target = $wallets->get((int) $data['id_dompet_tujuan']);

                abort_unless($source?->id_user === $userId && $target !== null, 403);

                /* Transfer antar-user hanya boleh ke dompet yang diaktifkan
                 * sebagai penerima transfer (terima_transfer = true). */
                if ((int) $target->id_user !== $userId && ! (bool) $target->terima_transfer) {
                    abort(403, 'Dompet tujuan belum diaktifkan untuk menerima transfer.');
                }

                $amount = CurrencyService::toBase((float) $data['jumlah']);
                if ((float) $source->saldo < $amount) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Saldo dompet asal tidak mencukupi.',
                    ]);
                }

                $transfer = Transfer::create([
                    'id_user' => $userId,
                    'id_dompet_asal' => $source->id_dompet,
                    'id_dompet_tujuan' => $target->id_dompet,
                    'jumlah' => $amount,
                    'catatan' => $data['catatan'] ?? null,
                    'tanggal_transfer' => $data['tanggal_transfer'],
                ]);

                $saldoTujuanSebelumnya = (float) $target->saldo;
                $source->update(['saldo' => (float) $source->saldo - $amount]);
                $target->update(['saldo' => (float) $target->saldo + $amount]);

                $transfer->transaksi()->createMany([
                    [
                        'id_dompet' => $source->id_dompet,
                        'nama_transaksi' => 'Transfer ke '.$target->nama_dompet,
                        'jumlah' => $amount,
                        'jenis' => 'pengeluaran',
                        'tanggal' => $data['tanggal_transfer'],
                    ],
                    [
                        'id_dompet' => $target->id_dompet,
                        'nama_transaksi' => 'Transfer dari '.$source->nama_dompet,
                        'jumlah' => $amount,
                        'jenis' => 'pemasukan',
                        'tanggal' => $data['tanggal_transfer'],
                    ],
                ]);

                TargetTabungan::where('id_user', $target->id_user)
                    ->where('id_dompet', $target->id_dompet)
                    ->get()
                    ->each(fn (TargetTabungan $targetTabungan) => $notifikasiService->targetBaruTercapai($targetTabungan, $saldoTujuanSebelumnya));
                $notifikasiService->transferBerhasil($transfer);
            });
        } catch (Throwable $e) {
            /* Proses gagal (mis. saldo kurang) — buka lagi pintu retry-nya. */
            RateLimiter::clear($dupKey);
            throw $e;
        }

        return redirect()->route('transfer.index');
    }

    public function show(Transfer $transfer): View
    {
        $userId = (int) Auth::id();
        abort_unless(
            $transfer->id_user === $userId || $transfer->dompetTujuan()->where('id_user', $userId)->exists(),
            404
        );

        return view('transfer.show', ['transfer' => $transfer->load(['dompetAsal.user', 'dompetTujuan.user', 'transaksi'])]);
    }

    private function immutable(): never
    {
        abort(405, 'Transfer tidak dapat diubah atau dihapus.');
    }

    public function edit(): never
    {
        $this->immutable();
    }

    public function update(): never
    {
        $this->immutable();
    }

    public function destroy(): never
    {
        $this->immutable();
    }
}
