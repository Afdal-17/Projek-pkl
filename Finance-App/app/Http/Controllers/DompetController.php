<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Http\Requests\StoreDompetRequest;
use App\Http\Requests\UpdateDompetRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DompetController extends Controller
{
    public function index(): View
    {
        return view('dompet.index', ['dompet' => Dompet::where('id_user', Auth::id())->latest()->paginate(10)]);
    }

    public function create(): View
    {
        return view('dompet.create');
    }

    public function frontendShow(Dompet $dompet): View
    {
        abort_unless($dompet->id_user === (int) Auth::id(), 404);

        $icons = ['makanan' => 'utensils', 'transport' => 'car', 'hiburan' => 'film', 'tagihan' => 'landmark', 'gaji' => 'landmark'];

        $transactions = Transaksi::with(['kategori', 'transfer.dompetAsal', 'transfer.dompetTujuan'])
            ->where('id_dompet', $dompet->id_dompet)
            ->latest('tanggal')
            ->get()
            ->map(fn (Transaksi $transaction): array => [
                'id' => $transaction->id_transaksi,
                'name' => $transaction->nama_transaksi,
                'type' => $transaction->id_transfer !== null
                    ? 'transfer'
                    : ($transaction->jenis === 'pemasukan' ? 'income' : 'expense'),
                'category' => $transaction->id_transfer !== null
                    ? 'Transfer'
                    : ($transaction->kategori?->nama_kategori ?? 'Without category'),
                'amount' => (float) $transaction->jumlah,
                'jenis' => $transaction->jenis,
                'icon' => $transaction->id_transfer !== null
                    ? 'transfer'
                    : ($transaction->kategori?->icon ?: ($icons[strtolower($transaction->kategori?->nama_kategori ?? '')] ?? ($transaction->jenis === 'pemasukan' ? 'landmark' : 'utensils'))),
                'date' => $transaction->tanggal->toDateString(),
            ]);

        return view('dompet.history', [
            'dompet' => $dompet,
            'transactions' => $transactions,
        ]);
    }

    public function store(StoreDompetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        Dompet::create([...$data, 'id_user' => Auth::id(), 'saldo' => $data['saldo_awal']]);
        return redirect()->route('dompet.index');
    }

    public function show(Dompet $dompet): View
    {
        abort_unless($dompet->id_user === Auth::id(), 404);
        return view('dompet.show', compact('dompet'));
    }

    public function edit(Dompet $dompet): View
    {
        abort_unless($dompet->id_user === Auth::id(), 404);
        return view('dompet.edit', compact('dompet'));
    }

    public function update(UpdateDompetRequest $request, Dompet $dompet): RedirectResponse
    {
        abort_unless($dompet->id_user === Auth::id(), 404);
        $dompet->update($request->validated());
        return redirect()->route('dompet.index');
    }

    public function frontendUpdate(Request $request, Dompet $dompet, NotifikasiService $notifikasiService): JsonResponse
    {
        abort_unless($dompet->id_user === (int) Auth::id(), 404);

        $data = $request->validate([
            'nama_dompet' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'jenis' => ['required', 'string', 'in:physical,digital,bank'],
            'warna' => ['required', 'string', 'regex:/^(brand|income|warn|expense|dark|#[0-9a-fA-F]{6})$/'],
            'saldo' => ['required', 'numeric', 'min:0'],
        ]);

        $data['warna'] = strtolower($data['warna']);

        $warnaDipakai = Dompet::where('id_user', Auth::id())
            ->where('warna', $data['warna'])
            ->where('id_dompet', '!=', $dompet->id_dompet)
            ->exists();

        if ($warnaDipakai) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'warna' => 'Wallet tidak bisa diedit karena warna penentu sama.',
            ]);
        }

        DB::transaction(function () use ($data, $dompet, $notifikasiService): void {
            $wallet = Dompet::where('id_user', Auth::id())
                ->whereKey($dompet->id_dompet)
                ->lockForUpdate()
                ->firstOrFail();
            $oldBalance = (float) $wallet->saldo;
            $newBalance = (float) $data['saldo'];
            $delta = round($newBalance - $oldBalance, 2);

            $wallet->update([
                'nama_dompet' => $data['nama_dompet'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'jenis' => $data['jenis'],
                'warna' => $data['warna'],
                'saldo' => $newBalance,
            ]);

            if ($delta !== 0.0) {
                Transaksi::create([
                    'id_kategori' => null,
                    'id_dompet' => $wallet->id_dompet,
                    'nama_transaksi' => 'Penyesuaian saldo wallet',
                    'jumlah' => abs($delta),
                    'jenis' => $delta > 0 ? 'pemasukan' : 'pengeluaran',
                    'tanggal' => now(),
                ]);

                TargetTabungan::where('id_user', $wallet->id_user)
                    ->where('id_dompet', $wallet->id_dompet)
                    ->get()
                    ->each(fn (TargetTabungan $target) => $notifikasiService->targetBaruTercapai($target, $oldBalance));
            }
        });

        return response()->json(['status' => 'wallet-updated']);
    }

    public function destroy(Request $request, Dompet $dompet): RedirectResponse|JsonResponse
    {
        abort_unless($dompet->id_user === (int) Auth::id(), 404);

        $transferCount = Transfer::where('id_dompet_asal', $dompet->id_dompet)
            ->orWhere('id_dompet_tujuan', $dompet->id_dompet)
            ->count();

        if ($transferCount > 0 && !$request->boolean('force')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Wallet tidak dapat dihapus karena masih memiliki riwayat transfer.',
                    'transfer_count' => $transferCount,
                ], 422);
            }

            return redirect()->back()->with('error', 'Wallet tidak dapat dihapus karena masih memiliki riwayat transfer.');
        }

        if ($transferCount > 0) {
            Transfer::where('id_dompet_asal', $dompet->id_dompet)
                ->orWhere('id_dompet_tujuan', $dompet->id_dompet)
                ->delete();
        }

        $dompet->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'wallet-deleted']);
        }

        return redirect()->back()->with('status', 'Wallet telah dihapus.');
    }
}
