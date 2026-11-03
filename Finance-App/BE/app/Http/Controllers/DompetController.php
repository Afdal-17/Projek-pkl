<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDompetRequest;
use App\Http\Requests\UpdateDompetRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Services\CurrencyService;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        $transactions = Transaksi::with(['dompet', 'kategori', 'transfer.dompetAsal.user', 'transfer.dompetTujuan.user'])
            ->where('id_dompet', $dompet->id_dompet)
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
        $saldoAwal = isset($data['saldo_awal']) && $data['saldo_awal'] !== null && $data['saldo_awal'] !== ''
            ? CurrencyService::toBase((float) $data['saldo_awal'])
            : 0.0;
        $saldo = isset($data['saldo']) && $data['saldo'] !== null && $data['saldo'] !== ''
            ? CurrencyService::toBase((float) $data['saldo'])
            : $saldoAwal;

        // Warna tidak dikirim form (create.blade.php); pilih warna pertama yang
        // belum dipakai agar dompet tetap mudah dibedakan.
        if (empty($data['warna'])) {
            $warnaDipakai = Dompet::where('id_user', Auth::id())->pluck('warna')->all();
            $data['warna'] = collect(['brand', 'income', 'warn', 'expense', 'dark'])
                ->first(fn (string $warna): bool => ! in_array($warna, $warnaDipakai, true)) ?? 'brand';
        }

        DB::transaction(function () use ($data, $saldoAwal, $saldo): void {
            $dompet = Dompet::create([
                ...$data,
                'id_user' => Auth::id(),
                'saldo_awal' => $saldoAwal,
                'saldo' => $saldo,
            ]);

            if ($saldoAwal > 0) {
                Transaksi::create([
                    'id_kategori' => null,
                    'id_dompet' => $dompet->id_dompet,
                    'nama_transaksi' => 'Saldo awal',
                    'jumlah' => $saldoAwal,
                    'jenis' => 'pemasukan',
                    'tanggal' => now(),
                ]);
            }
        });

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

        $newBalance = CurrencyService::toBase((float) $data['saldo']);

        DB::transaction(function () use ($data, $dompet, $notifikasiService, $newBalance): void {
            $wallet = Dompet::where('id_user', Auth::id())
                ->whereKey($dompet->id_dompet)
                ->lockForUpdate()
                ->firstOrFail();
            $oldBalance = (float) $wallet->saldo;
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

    public function toggleTransfer(Request $request, Dompet $dompet): JsonResponse
    {
        abort_unless($dompet->id_user === (int) Auth::id(), 404);

        if (! $dompet->terima_transfer) {
            $dompet->setSebagaiPenerimaTransfer();
        } else {
            $dompet->update(['terima_transfer' => false]);
        }

        return response()->json([
            'status' => 'ok',
            'terima_transfer' => (bool) $dompet->fresh()->terima_transfer,
        ]);
    }

    public function destroy(Request $request, Dompet $dompet): RedirectResponse|JsonResponse
    {
        abort_unless($dompet->id_user === (int) Auth::id(), 404);

        $idDompetDihapus = (int) $dompet->id_dompet;

        $transfers = Transfer::where('id_dompet_asal', $idDompetDihapus)
            ->orWhere('id_dompet_tujuan', $idDompetDihapus)
            ->get();

        if ($transfers->isNotEmpty() && ! $request->boolean('force')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Wallet tidak dapat dihapus karena masih memiliki riwayat transfer.',
                    'transfer_count' => $transfers->count(),
                ], 422);
            }

            return redirect()->back()->with('error', 'Wallet tidak dapat dihapus karena masih memiliki riwayat transfer.');
        }

        // Force delete hanya aman bila SEMUA riwayat transfer melibatkan dompet
        // milik user ini. Jika menyentuh dompet user lain, riwayat mereka tidak
        // boleh dihapus dan saldo mereka tidak boleh diubah oleh user ini.
        $idDompetLain = $transfers->map(fn (Transfer $transfer): ?int => (int) $transfer->id_dompet_asal === $idDompetDihapus
            ? ($transfer->id_dompet_tujuan !== null ? (int) $transfer->id_dompet_tujuan : null)
            : (int) $transfer->id_dompet_asal)
            ->filter()
            ->unique()
            ->values();

        $melibatkanUserLain = Dompet::whereIn('id_dompet', $idDompetLain)
            ->where('id_user', '!=', (int) $dompet->id_user)
            ->exists();

        if ($melibatkanUserLain) {
            $message = 'Wallet tidak dapat dihapus paksa karena riwayat transfernya melibatkan dompet pengguna lain.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        DB::transaction(function () use ($dompet, $transfers, $idDompetDihapus): void {
            foreach ($transfers as $transfer) {
                $idDompetTetap = (int) $transfer->id_dompet_asal === $idDompetDihapus
                    ? $transfer->id_dompet_tujuan
                    : $transfer->id_dompet_asal;

                // Batalkan efek transfer pada dompet yang tetap ada, agar saldo
                // tidak menyimpang dari riwayat transaksi yang tersisa.
                $dompetTetap = Dompet::whereKey($idDompetTetap)->lockForUpdate()->first();

                if ($dompetTetap !== null) {
                    $delta = (int) $transfer->id_dompet_tujuan === $idDompetDihapus
                        ? -(float) $transfer->jumlah
                        : (float) $transfer->jumlah;

                    $dompetTetap->update(['saldo' => (float) $dompetTetap->saldo + $delta]);
                }

                // Hapus dua baris transaksi turunan (dompet asal & tujuan) lebih
                // dulu; jika tidak, id_transfer-nya jadi NULL dan baris "Transfer
                // dari ..." di dompet lain dihitung sebagai pemasukan biasa.
                $transfer->transaksi()->delete();
                $transfer->delete();
            }

            $dompet->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => 'wallet-deleted']);
        }

        return redirect()->back()->with('status', 'Wallet telah dihapus.');
    }
}
