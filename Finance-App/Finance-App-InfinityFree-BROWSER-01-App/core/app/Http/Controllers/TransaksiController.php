<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterTransaksiRequest;
use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Requests\UpdateTransaksiRequest;
use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Services\CurrencyService;
use App\Services\DuplicateSubmissionGuard;
use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TransaksiController extends Controller
{
    /**
     * Jendela deteksi kiriman kembar (tap ganda saat loading).
     * Dalam rentang ini, payload identik dari user yang sama hanya dicatat sekali.
     */
    private const DUPLICATE_GUARD_SECONDS = 30;

    public function frontendIndex(): View
    {
        $userId = (int) Auth::id();
        $icons = ['makanan' => 'utensils', 'transport' => 'car', 'hiburan' => 'film', 'tagihan' => 'landmark', 'gaji' => 'landmark'];

        $transactions = Transaksi::with(['dompet', 'kategori', 'transfer.dompetAsal.user', 'transfer.dompetTujuan.user'])
            ->whereHas('dompet', fn ($query) => $query->where('id_user', $userId))
            ->latest('tanggal')
            ->get()
            ->map(fn (Transaksi $transaction): array => [
                'id' => $transaction->id_transaksi,
                'name' => $transaction->displayNama(),
                'type' => $transaction->id_transfer !== null
                    ? 'transfer'
                    : ($transaction->jenis === 'pemasukan' ? 'income' : 'expense'),
                'category' => $transaction->id_transfer !== null
                    ? 'Transfer'
                    : ($transaction->kategori?->nama_kategori ?? ($transaction->nama_transaksi === 'Saldo awal' ? 'Saldo awal' : 'Without category')),
                'wallet' => $transaction->dompet->nama_dompet,
                'to' => $transaction->id_transfer !== null
                    ? $transaction->displayLawan()
                    : null,
                'amount' => (string) $transaction->jumlah,
                'date' => $transaction->tanggal->toDateString(),
                'jenis' => $transaction->jenis,
                'icon' => $transaction->id_transfer !== null
                    ? 'transfer'
                    : ($transaction->kategori?->icon ?: ($icons[strtolower($transaction->kategori?->nama_kategori ?? '')] ?? ($transaction->jenis === 'pemasukan' ? 'landmark' : 'utensils'))),
            ]);

        return view('transactions.index', [
            'transactions' => $transactions,
            ...$this->frontendOptions($userId),
        ]);
    }

    public function frontendCreate(): View
    {
        return view('transactions.create', $this->frontendOptions((int) Auth::id()));
    }

    public function index(FilterTransaksiRequest $request): View
    {
        $userId = (int) Auth::id();
        $filters = $request->validated();
        $query = Transaksi::with(['dompet', 'kategori', 'transfer.dompetAsal.user', 'transfer.dompetTujuan.user'])
            ->whereHas('dompet', fn ($query) => $query->where('id_user', $userId));

        $query
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where('nama_transaksi', 'like', '%'.$value.'%'))
            ->when($filters['jenis'] ?? null, fn ($query, $value) => $query->where('jenis', $value))
            ->when($filters['id_dompet'] ?? null, fn ($query, $value) => $query->where('id_dompet', $value))
            ->when($filters['id_kategori'] ?? null, fn ($query, $value) => $query->where('id_kategori', $value))
            ->when($filters['tanggal_mulai'] ?? null, fn ($query, $value) => $query->whereDate('tanggal', '>=', $value))
            ->when($filters['tanggal_selesai'] ?? null, fn ($query, $value) => $query->whereDate('tanggal', '<=', $value));

        return view('transaksi.index', [
            'transaksi' => $query->latest('tanggal')->paginate(10)->withQueryString(),
            'dompet' => Dompet::where('id_user', $userId)->orderBy('nama_dompet')->get(),
            'kategori' => Kategori::where('id_user', $userId)->orderBy('nama_kategori')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('transaksi.create', [
            'dompet' => Dompet::where('id_user', Auth::id())->orderBy('nama_dompet')->get(),
            'kategori' => Kategori::where('id_user', Auth::id())->orderBy('nama_kategori')->get(),
        ]);
    }

    public function store(
        StoreTransaksiRequest $request,
        NotifikasiService $notifikasiService,
        DuplicateSubmissionGuard $duplicateGuard
    ): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) Auth::id();

        /* Tolak kiriman kembar (mis. tap ganda saat loading). Slot dipesan SEBELUM
         * proses insert, sehingga tap kedua yang datang bersamaan pun ikut terblokir. */
        $dupKey = 'transaksi-duplikat:'.$userId.':'.sha1(json_encode([
            $data['id_dompet'],
            $data['id_kategori'],
            $data['nama_transaksi'],
            bcadd((string) $data['jumlah'], '0', 2),
            $data['jenis'],
            $data['tanggal'],
        ]));

        if (! $duplicateGuard->reserve($dupKey, self::DUPLICATE_GUARD_SECONDS)) {
            throw ValidationException::withMessages([
                'nama_transaksi' => 'Transaksi ini baru saja dicatat — hanya satu yang tersimpan. '
                    .'Jika memang ingin membuat transaksi serupa, ubah sedikit nama atau jumlahnya, '
                    .'atau tunggu '.self::DUPLICATE_GUARD_SECONDS.' detik.',
            ]);
        }

        try {
            $data['jumlah'] = CurrencyService::toBaseExact((string) $data['jumlah']);

            if (! CurrencyService::baseAmountWithinStorageLimit($data['jumlah'])) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Jumlah setelah konversi melebihi batas nominal yang didukung.',
                ]);
            }

            DB::transaction(function () use ($data, $userId, $notifikasiService): void {
                $dompet = Dompet::where('id_user', $userId)
                    ->whereKey($data['id_dompet'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $saldoSebelumnya = (string) $dompet->saldo;

                $this->applyBalanceChange($dompet, $data['jenis'], (string) $data['jumlah']);
                $transaksi = Transaksi::create($data);
                $this->notifyReachedTargets($dompet, $saldoSebelumnya, $notifikasiService);
                $notifikasiService->transaksiDicatat($transaksi, $userId);
            });
        } catch (Throwable $e) {
            /* Proses gagal (mis. saldo kurang) — buka lagi pintu retry-nya. */
            $duplicateGuard->release($dupKey);
            throw $e;
        }

        return redirect()->route('transaksi.index');
    }

    public function show(Transaksi $transaksi): View
    {
        $transaksi = $this->ownedTransaction($transaksi);

        return view('transaksi.show', compact('transaksi'));
    }

    public function edit(Transaksi $transaksi): View
    {
        $transaksi = $this->ownedTransaction($transaksi);
        abort_if($transaksi->id_transfer !== null, 405, 'Transaksi transfer tidak dapat diubah.');

        return view('transaksi.edit', [
            'transaksi' => $transaksi,
            'dompet' => Dompet::where('id_user', Auth::id())->orderBy('nama_dompet')->get(),
            'kategori' => Kategori::where('id_user', Auth::id())->orderBy('nama_kategori')->get(),
        ]);
    }

    public function update(UpdateTransaksiRequest $request, Transaksi $transaksi, NotifikasiService $notifikasiService): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) Auth::id();
        $data['jumlah'] = CurrencyService::toBaseExact((string) $data['jumlah']);

        if (! CurrencyService::baseAmountWithinStorageLimit($data['jumlah'])) {
            throw ValidationException::withMessages([
                'jumlah' => 'Jumlah setelah konversi melebihi batas nominal yang didukung.',
            ]);
        }

        DB::transaction(function () use ($data, $transaksi, $userId, $notifikasiService): void {
            $current = Transaksi::whereHas('dompet', fn ($query) => $query->where('id_user', $userId))
                ->whereKey($transaksi->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($current->id_transfer !== null, 405, 'Transaksi transfer tidak dapat diubah.');
            $oldDompet = Dompet::where('id_user', $userId)
                ->whereKey($current->id_dompet)
                ->lockForUpdate()
                ->firstOrFail();
            $saldoLama = (string) $oldDompet->saldo;
            $newDompet = $current->id_dompet === (int) $data['id_dompet']
                ? $oldDompet
                : Dompet::where('id_user', $userId)
                    ->whereKey($data['id_dompet'])
                    ->lockForUpdate()
                    ->firstOrFail();
            $saldoDompetBaru = (string) $newDompet->saldo;

            $this->applyBalanceChange($oldDompet, $current->jenis === 'pemasukan' ? 'pengeluaran' : 'pemasukan', (string) $current->jumlah);
            $this->applyBalanceChange($newDompet, $data['jenis'], (string) $data['jumlah']);
            $current->update($data);
            $this->notifyReachedTargets($oldDompet, $saldoLama, $notifikasiService);
            if ($newDompet->id_dompet !== $oldDompet->id_dompet) {
                $this->notifyReachedTargets($newDompet, $saldoDompetBaru, $notifikasiService);
            }
            $notifikasiService->transaksiDiubah($current->fresh(), $userId);
        });

        return redirect()->route('transaksi.index');
    }

    public function destroy(Transaksi $transaksi, NotifikasiService $notifikasiService): RedirectResponse
    {
        $userId = (int) Auth::id();

        DB::transaction(function () use ($transaksi, $userId, $notifikasiService): void {
            $current = Transaksi::whereHas('dompet', fn ($query) => $query->where('id_user', $userId))
                ->whereKey($transaksi->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($current->id_transfer !== null, 405, 'Transaksi transfer tidak dapat dihapus.');
            $dompet = Dompet::where('id_user', $userId)
                ->whereKey($current->id_dompet)
                ->lockForUpdate()
                ->firstOrFail();
            $saldoSebelumnya = (string) $dompet->saldo;

            $this->applyBalanceChange($dompet, $current->jenis === 'pemasukan' ? 'pengeluaran' : 'pemasukan', (string) $current->jumlah);
            $namaTransaksi = $current->nama_transaksi;
            $jumlahTransaksi = (string) $current->jumlah;
            $current->delete();
            $this->notifyReachedTargets($dompet, $saldoSebelumnya, $notifikasiService);
            $notifikasiService->transaksiDihapus($namaTransaksi, $jumlahTransaksi, $userId);
        });

        return redirect()->route('transaksi.index');
    }

    private function ownedTransaction(Transaksi $transaksi): Transaksi
    {
        abort_unless(
            $transaksi->dompet()->where('id_user', Auth::id())->exists(),
            404
        );

        return $transaksi->load(['dompet', 'kategori', 'transfer.dompetAsal.user', 'transfer.dompetTujuan.user']);
    }

    private function applyBalanceChange(Dompet $dompet, string $jenis, string $amount): void
    {
        $newBalance = $jenis === 'pemasukan'
            ? bcadd((string) $dompet->saldo, $amount, 2)
            : bcsub((string) $dompet->saldo, $amount, 2);

        if (bccomp($newBalance, '0.00', 2) < 0) {
            throw ValidationException::withMessages([
                'jumlah' => 'Saldo dompet tidak mencukupi untuk transaksi ini.',
            ]);
        }

        $dompet->update(['saldo' => $newBalance]);
    }

    private function notifyReachedTargets(Dompet $dompet, string $saldoSebelumnya, NotifikasiService $notifikasiService): void
    {
        TargetTabungan::where('id_user', $dompet->id_user)
            ->where('id_dompet', $dompet->id_dompet)
            ->get()
            ->each(fn (TargetTabungan $target) => $notifikasiService->targetBaruTercapai($target, $saldoSebelumnya));
    }

    private function frontendOptions(int $userId): array
    {
        $icons = ['makanan' => 'utensils', 'transport' => 'car', 'hiburan' => 'film', 'tagihan' => 'landmark', 'gaji' => 'landmark'];

        return [
            'categories' => Kategori::where('id_user', $userId)
                ->orderBy('nama_kategori')
                ->get()
                ->map(fn (Kategori $category): array => [
                    'id' => $category->id_kategori,
                    'name' => $category->nama_kategori,
                    'type' => $category->jenis === 'pemasukan' ? 'income' : 'expense',
                    'icon' => $category->icon ?: ($icons[strtolower($category->nama_kategori)] ?? 'landmark'),
                ]),
            'wallets' => Dompet::where('id_user', $userId)
                ->orderBy('nama_dompet')
                ->get()
                ->map(fn (Dompet $wallet): array => [
                    'id' => $wallet->id_dompet,
                    'name' => $wallet->nama_dompet,
                    'balance' => (string) $wallet->saldo,
                ]),
        ];
    }
}
