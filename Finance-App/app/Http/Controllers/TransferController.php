<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use App\Models\Transfer;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\DuplicateSubmissionGuard;
use App\Services\NotifikasiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                'balance' => (string) $wallet->saldo,
            ]);

        return view('transfer', compact('wallets'));
    }

    public function frontendRecipients(): JsonResponse
    {
        $query = trim((string) request('query'));
        abort_if($query === '', 422, 'Enter recipient name, email, or ID.');

        $recipient = User::query()
            ->where('status', true)
            ->where('role', 'user')
            ->whereNotNull('email_verified_at')
            ->whereNull('banned_at')
            ->where(function (Builder $queryBuilder) use ($query): void {
                $queryBuilder->where('email', $query)
                    ->orWhere('nama', $query)
                    ->orWhere('id_user', ctype_digit($query) ? (int) $query : 0);
            })
            ->whereHas('dompet', fn ($wallets) => $wallets->where('terima_transfer', true))
            ->with(['dompet' => fn ($wallets) => $wallets
                ->select('id_dompet', 'id_user', 'nama_dompet', 'terima_transfer')
                ->where('terima_transfer', true)
                ->orderBy('nama_dompet')])
            ->first();

        abort_if($recipient === null, 404, 'User not found or has no active wallet.');

        $wallet = $recipient->dompet->where('terima_transfer', true)->first();

        abort_if($wallet === null, 404, 'Recipient has not enabled any wallet for transfers.');

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
            ->where('status', true)
            ->whereNotNull('email_verified_at')
            ->whereNull('banned_at')
            ->where('id_user', '!=', (int) Auth::id())
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('nama', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%')
                    ->orWhere('id_user', ctype_digit($query) ? (int) $query : 0);
            })
            ->with(['dompet' => fn ($wallets) => $wallets
                ->select('id_dompet', 'id_user', 'nama_dompet', 'terima_transfer')
                ->orderBy('nama_dompet')])
            ->limit(8)
            ->get();

        return response()->json(
            $users->map(function (User $user): array {
                $hasAnyWallet = $user->dompet->isNotEmpty();
                $walletsAktif = $user->dompet->where('terima_transfer', true)
                    ->sortBy('nama_dompet')
                    ->values();

                return [
                    'id_user' => $user->id_user,
                    'nama' => $user->nama,
                    'is_banned' => $user->isBanned(),
                    'has_any_wallet' => $hasAnyWallet,
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

    public function store(
        StoreTransferRequest $request,
        NotifikasiService $notifikasiService,
        DuplicateSubmissionGuard $duplicateGuard
    ): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) Auth::id();
        $sender = Auth::user();

        abort_if(
            $sender === null
            || $sender->isBanned()
            || ! $sender->status
            || $sender->email_verified_at === null,
            403,
            'Sender account is inactive, unverified, or suspended.'
        );

        /* Gunakan token idempotensi eksplisit jika dikirim frontend/API,
         * atau fingerprint payload unik untuk mencegah double-click/spam. */
        $rawIdempotency = $request->input('idempotency_key')
            ?? $request->header('X-Idempotency-Key');

        $idempotencyKeyHash = $rawIdempotency !== null && trim((string) $rawIdempotency) !== ''
            ? hash('sha256', trim((string) $rawIdempotency))
            : null;
        $requestHash = $idempotencyKeyHash === null
            ? null
            : hash('sha256', json_encode([
                $userId,
                (int) $data['id_dompet_asal'],
                (int) $data['id_dompet_tujuan'],
                bcadd((string) $data['jumlah'], '0', 2),
                $data['tanggal_transfer'],
                $data['catatan'] ?? null,
            ], JSON_THROW_ON_ERROR));

        $idempotencyIdentity = $idempotencyKeyHash !== null
            ? 'token:'.$idempotencyKeyHash
            : 'hash:'.sha1(json_encode([
                $data['id_dompet_asal'],
                $data['id_dompet_tujuan'],
                bcadd((string) $data['jumlah'], '0', 2),
                $data['tanggal_transfer'],
                $data['catatan'] ?? null,
            ]));

        $dupKey = 'transfer-idempotency:'.$userId.':'.$idempotencyIdentity;

        if (! $duplicateGuard->reserve($dupKey, self::DUPLICATE_GUARD_SECONDS)) {
            throw ValidationException::withMessages([
                'jumlah' => 'This transfer was just processed — only one was saved. '
                    .'If intentional, please wait '.self::DUPLICATE_GUARD_SECONDS.' seconds before retrying.',
            ]);
        }

        // Variabel yang di-pass by reference ke dalam DB::transaction closure.
        $transfer = null;
        $saldoTujuanSebelumnya = null;
        $targetTabungans = collect();

        try {
            // ─── TAHAP 1: Transaksi keuangan (atomik) ────────────────────────────
            // Semua operasi keuangan (debit, kredit, pencatatan) dalam satu
            // DB::transaction agar tetap konsisten. Notifikasi dipindah ke luar
            // agar kegagalan skema notifikasi tidak membatalkan transfer yang berhasil.
            DB::transaction(function () use (
                $data,
                $userId,
                $idempotencyKeyHash,
                $requestHash,
                &$transfer,
                &$saldoTujuanSebelumnya,
                &$targetTabungans
            ): void {
                if ($idempotencyKeyHash !== null) {
                    DB::table('transfer_idempotency_keys')->insert([
                        'id_user' => $userId,
                        'key_hash' => $idempotencyKeyHash,
                        'request_hash' => $requestHash,
                        'id_transfer' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $wallets = Dompet::whereIn('id_dompet', [$data['id_dompet_asal'], $data['id_dompet_tujuan']])
                    ->orderBy('id_dompet')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id_dompet');
                $source = $wallets->get((int) $data['id_dompet_asal']);
                $target = $wallets->get((int) $data['id_dompet_tujuan']);

                abort_unless($source?->id_user === $userId && $target !== null, 403);
                abort_if((int) $source->id_dompet === (int) $target->id_dompet, 422, 'Destination wallet must be different from source wallet.');

                /* Transfer antar-user hanya boleh ke dompet yang diaktifkan
                 * sebagai penerima transfer (terima_transfer = true). */
                if ((int) $target->id_user !== $userId) {
                    $recipient = User::query()
                        ->whereKey($target->id_user)
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_if(
                        $recipient->isBanned()
                        || ! $recipient->status
                        || $recipient->role !== 'user'
                        || $recipient->email_verified_at === null,
                        422,
                        'Recipient account is inactive or unverified.'
                    );
                    abort_unless((bool) $target->terima_transfer, 422, 'Destination wallet is not enabled to receive transfers.');
                }

                $amount = CurrencyService::toBaseExact((string) $data['jumlah']);
                if (! CurrencyService::baseAmountWithinStorageLimit($amount)) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Converted amount exceeds the maximum supported limit.',
                    ]);
                }

                if (bccomp((string) $source->saldo, $amount, 2) < 0) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Insufficient balance in source wallet.',
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

                if ($idempotencyKeyHash !== null) {
                    $updated = DB::table('transfer_idempotency_keys')
                        ->where('id_user', $userId)
                        ->where('key_hash', $idempotencyKeyHash)
                        ->update([
                            'id_transfer' => $transfer->id_transfer,
                            'updated_at' => now(),
                        ]);

                    if ($updated !== 1) {
                        throw new \RuntimeException('Transfer idempotency record could not be finalized.');
                    }
                }

                $saldoTujuanSebelumnya = (string) $target->saldo;
                $source->update(['saldo' => bcsub((string) $source->saldo, $amount, 2)]);
                $target->update(['saldo' => bcadd((string) $target->saldo, $amount, 2)]);

                $transfer->transaksi()->createMany([
                    [
                        'id_dompet' => $source->id_dompet,
                        'nama_transaksi' => 'Transfer to '.$target->nama_dompet,
                        'jumlah' => $amount,
                        'jenis' => 'pengeluaran',
                        'tanggal' => $data['tanggal_transfer'],
                    ],
                    [
                        'id_dompet' => $target->id_dompet,
                        'nama_transaksi' => 'Transfer from '.$source->nama_dompet,
                        'jumlah' => $amount,
                        'jenis' => 'pemasukan',
                        'tanggal' => $data['tanggal_transfer'],
                    ],
                ]);

                // Kumpulkan target tabungan yang perlu dicek setelah transfer.
                // Ini dilakukan di luar transaction agar kegagalan notifikasi tidak
                // membatalkan transaksi keuangan yang sudah berhasil.
                $targetTabungans = TargetTabungan::where('id_user', $target->id_user)
                    ->where('id_dompet', $target->id_dompet)
                    ->get();
            });
        } catch (QueryException $e) {
            $duplicateGuard->release($dupKey);

            $existingRequestHash = $idempotencyKeyHash === null
                ? null
                : DB::table('transfer_idempotency_keys')
                    ->where('id_user', $userId)
                    ->where('key_hash', $idempotencyKeyHash)
                    ->value('request_hash');

            if ($existingRequestHash !== null) {
                $message = hash_equals((string) $existingRequestHash, (string) $requestHash)
                    ? 'This transfer was already completed; the same request was not repeated.'
                    : 'This idempotency key was already used for a different transfer request.';

                throw ValidationException::withMessages([
                    'jumlah' => $message,
                ]);
            }

            throw $e;
        } catch (Throwable $e) {
            /* Proses keuangan gagal (mis. saldo kurang) — buka lagi pintu retry-nya. */
            $duplicateGuard->release($dupKey);
            throw $e;
        }

        // ─── TAHAP 2: Notifikasi (di LUAR transaction keuangan) ─────────────────
        // Kegagalan notifikasi (mis. kolom `jumlah` belum di-migrate di hosting)
        // hanya dicatat di log — transfer yang sudah berhasil tidak dibatalkan.
        try {
            foreach ($targetTabungans as $targetTabungan) {
                $notifikasiService->targetBaruTercapai($targetTabungan, $saldoTujuanSebelumnya);
            }
            if ($transfer !== null) {
                $notifikasiService->transferBerhasil($transfer);
            }
        } catch (Throwable $notifError) {
            \Illuminate\Support\Facades\Log::warning('Transfer notification failed (financial transfer succeeded)', [
                'transfer_id' => $transfer?->id_transfer,
                'error' => $notifError->getMessage(),
            ]);
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
