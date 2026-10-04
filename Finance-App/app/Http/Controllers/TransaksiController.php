<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Requests\UpdateTransaksiRequest;
use App\Http\Requests\FilterTransaksiRequest;
use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\Transaksi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransaksiController extends Controller
{
    public function index(FilterTransaksiRequest $request): View
    {
        $userId = (int) Auth::id();
        $filters = $request->validated();
        $query = Transaksi::with(['dompet', 'kategori'])
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

    public function store(StoreTransaksiRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) Auth::id();

        DB::transaction(function () use ($data, $userId): void {
            $dompet = Dompet::where('id_user', $userId)
                ->whereKey($data['id_dompet'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyBalanceChange($dompet, $data['jenis'], (float) $data['jumlah']);
            Transaksi::create($data);
        });

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

        return view('transaksi.edit', [
            'transaksi' => $transaksi,
            'dompet' => Dompet::where('id_user', Auth::id())->orderBy('nama_dompet')->get(),
            'kategori' => Kategori::where('id_user', Auth::id())->orderBy('nama_kategori')->get(),
        ]);
    }

    public function update(UpdateTransaksiRequest $request, Transaksi $transaksi): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) Auth::id();

        DB::transaction(function () use ($data, $transaksi, $userId): void {
            $current = Transaksi::whereHas('dompet', fn ($query) => $query->where('id_user', $userId))
                ->whereKey($transaksi->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $oldDompet = Dompet::where('id_user', $userId)
                ->whereKey($current->id_dompet)
                ->lockForUpdate()
                ->firstOrFail();
            $newDompet = $current->id_dompet === (int) $data['id_dompet']
                ? $oldDompet
                : Dompet::where('id_user', $userId)
                    ->whereKey($data['id_dompet'])
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->applyBalanceChange($oldDompet, $current->jenis === 'pemasukan' ? 'pengeluaran' : 'pemasukan', (float) $current->jumlah);
            $this->applyBalanceChange($newDompet, $data['jenis'], (float) $data['jumlah']);
            $current->update($data);
        });

        return redirect()->route('transaksi.index');
    }

    public function destroy(Transaksi $transaksi): RedirectResponse
    {
        $userId = (int) Auth::id();

        DB::transaction(function () use ($transaksi, $userId): void {
            $current = Transaksi::whereHas('dompet', fn ($query) => $query->where('id_user', $userId))
                ->whereKey($transaksi->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $dompet = Dompet::where('id_user', $userId)
                ->whereKey($current->id_dompet)
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyBalanceChange($dompet, $current->jenis === 'pemasukan' ? 'pengeluaran' : 'pemasukan', (float) $current->jumlah);
            $current->delete();
        });

        return redirect()->route('transaksi.index');
    }

    private function ownedTransaction(Transaksi $transaksi): Transaksi
    {
        abort_unless(
            $transaksi->dompet()->where('id_user', Auth::id())->exists(),
            404
        );

        return $transaksi->load(['dompet', 'kategori']);
    }

    private function applyBalanceChange(Dompet $dompet, string $jenis, float $amount): void
    {
        $change = $jenis === 'pemasukan' ? $amount : -$amount;
        $newBalance = (float) $dompet->saldo + $change;

        if ($newBalance < 0) {
            throw ValidationException::withMessages([
                'jumlah' => 'Saldo dompet tidak mencukupi untuk transaksi ini.',
            ]);
        }

        $dompet->update(['saldo' => $newBalance]);
    }
}
