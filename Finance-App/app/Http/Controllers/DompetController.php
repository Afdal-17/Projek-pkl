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
use Illuminate\Validation\ValidationException;

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
                'amount' => (string) $transaction->jumlah,
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

        $currency = CurrencyService::normalize(
            Auth::user()->mata_uang ?? CurrencyService::BASE
        );

        $saldoAwal = $data['saldo_awal'] ?? null;
        $saldo = $data['saldo'] ?? null;

        // Saldo IDR tidak perlu dikonversi menggunakan float.
        // Untuk mata uang lain, konversi lama tetap digunakan sementara.
        if ($saldoAwal !== null && $saldoAwal !== '') {
            $saldoAwal = $currency === CurrencyService::BASE
                ? bcadd((string) $saldoAwal, '0', 2)
                : CurrencyService::toBaseExact((string) $saldoAwal, $currency);
        } else {
            $saldoAwal = '0.00';
        }

        if ($saldo !== null && $saldo !== '') {
            $saldo = $currency === CurrencyService::BASE
                ? bcadd((string) $saldo, '0', 2)
                : CurrencyService::toBaseExact((string) $saldo, $currency);
        } else {
            $saldo = $saldoAwal;
        }

        $this->validateSaldoBase($saldoAwal, 'saldo_awal');
        $this->validateSaldoBase($saldo, 'saldo');

        // Warna tidak dikirim form (create.blade.php); pilih warna awal yang
        // belum dipakai bila tersedia, tetapi warna tetap boleh digunakan ulang.
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

            if (bccomp($saldoAwal, '0.00', 2) > 0) {
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
            'saldo' => [
                'sometimes',
                'required',
                'regex:/^\d+(?:\.\d{1,2})?$/',
                'numeric',
                'min:0',
            ],
        ]);

        $data['warna'] = strtolower($data['warna']);

        $newBalance = null;
        if (array_key_exists('saldo', $data)) {
            $currency = CurrencyService::normalize(
                Auth::user()->mata_uang ?? CurrencyService::BASE
            );
            $newBalance = CurrencyService::toBaseExact($data['saldo'], $currency);
            $this->validateSaldoBase($newBalance, 'saldo');
        }

        DB::transaction(function () use ($data, $dompet, $notifikasiService, $newBalance): void {
            $wallet = Dompet::where('id_user', Auth::id())
                ->whereKey($dompet->id_dompet)
                ->lockForUpdate()
                ->firstOrFail();

            $oldBalance = (string) $wallet->saldo;
            $delta = $newBalance === null
                ? '0.00'
                : bcsub($newBalance, $oldBalance, 2);

            $updates = [
                'nama_dompet' => $data['nama_dompet'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'jenis' => $data['jenis'],
                'warna' => $data['warna'],
            ];
            if ($newBalance !== null) {
                $updates['saldo'] = $newBalance;
            }
            $wallet->update($updates);

            if (bccomp($delta, '0.00', 2) !== 0) {
                Transaksi::create([
                    'id_kategori' => null,
                    'id_dompet' => $wallet->id_dompet,
                    'nama_transaksi' => 'Penyesuaian saldo wallet',
                    'jumlah' => bccomp($delta, '0.00', 2) > 0
                        ? $delta
                        : bcsub('0.00', $delta, 2),
                    'jenis' => bccomp($delta, '0.00', 2) > 0
                        ? 'pemasukan'
                        : 'pengeluaran',
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

    public function setPrimaryTransfer(Request $request, Dompet $dompet): JsonResponse
    {
        abort_unless($dompet->id_user === (int) Auth::id(), 404);

        $request->validate([
            'set_primary' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($dompet): void {
            $wallets = Dompet::where('id_user', (int) $dompet->id_user)
                ->orderBy('id_dompet')
                ->lockForUpdate()
                ->get();
            $lockedWallet = $wallets->first(
                fn (Dompet $wallet): bool => (int) $wallet->id_dompet === (int) $dompet->id_dompet
            );

            abort_unless($lockedWallet !== null, 404);

            $lockedWallet->setSebagaiPenerimaTransfer();
        });

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
                    'message' => __('dompet.transfer_history_delete_blocked'),
                    'transfer_count' => $transfers->count(),
                ], 422);
            }

            return redirect()->back()->with('error', __('dompet.transfer_history_delete_blocked'));
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
            $message = __('dompet.other_users_transfer_delete_blocked');

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
                    $saldoBaru = (int) $transfer->id_dompet_tujuan === $idDompetDihapus
                        ? bcsub((string) $dompetTetap->saldo, (string) $transfer->jumlah, 2)
                        : bcadd((string) $dompetTetap->saldo, (string) $transfer->jumlah, 2);

                    $dompetTetap->update(['saldo' => $saldoBaru]);
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

        return redirect()->back()->with('status', __('dompet.deleted'));
    }
    private const MAX_SALDO = CurrencyService::MAX_BASE_AMOUNT;

    private function validateSaldoBase(string|int $amount, string $field): void
    {
        $amount = trim((string) $amount);

        if (
            ! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)
            || bccomp($amount, self::MAX_SALDO, 2) > 0
        ) {
            throw ValidationException::withMessages([
                $field => __('dompet.balance_limit'),
            ]);
        }
    }
}
