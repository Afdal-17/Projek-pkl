<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTargetTabunganRequest;
use App\Http\Requests\UpdateTargetTabunganRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use App\Services\CurrencyService;
use App\Services\DuplicateSubmissionGuard;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TargetTabunganController extends Controller
{
    /**
     * Jendela deteksi kiriman kembar (tap ganda saat loading).
     * Dalam rentang ini, payload identik dari user yang sama hanya dicatat sekali.
     */
    private const DUPLICATE_GUARD_SECONDS = 30;

    public function frontendIndex(): View
    {
        $targets = TargetTabungan::with('dompet')
            ->where('id_user', Auth::id())
            ->latest('id_target')
            ->get()
            ->map(fn (TargetTabungan $target): array => [
                'id' => $target->id_target,
                'name' => $target->nama_target,
                'note' => $target->deskripsi ?: $target->dompet->nama_dompet,
                'wallet' => $target->dompet->nama_dompet,
                'saved' => bccomp((string) $target->jumlah_terkumpul, (string) $target->nominal_target, 2) > 0
                    ? (string) $target->nominal_target
                    : (string) $target->jumlah_terkumpul,
                'target' => (string) $target->nominal_target,
            ]);

        return view('saving.index', compact('targets'));
    }

    public function frontendCreate(): View
    {
        return view('saving.create', [
            'wallets' => Dompet::where('id_user', Auth::id())
                ->orderBy('nama_dompet')
                ->get()
                ->map(fn (Dompet $wallet): array => [
                    'id' => $wallet->id_dompet,
                    'name' => $wallet->nama_dompet,
                    'balance' => (string) $wallet->saldo,
                ]),
        ]);
    }

    public function index(): View
    {
        return view('target-tabungan.index', [
            'targets' => TargetTabungan::with('dompet')
                ->where('id_user', Auth::id())
                ->latest('id_target')
                ->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('target-tabungan.create', [
            'dompet' => Dompet::where('id_user', Auth::id())->orderBy('nama_dompet')->get(),
        ]);
    }

    public function store(
        StoreTargetTabunganRequest $request,
        NotifikasiService $notifikasiService,
        DuplicateSubmissionGuard $duplicateGuard
    ): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $userId = (int) Auth::id();

        /* Tolak kiriman kembar (mis. tap ganda saat loading). Slot dipesan SEBELUM
         * proses insert, sehingga tap kedua yang datang bersamaan pun ikut terblokir. */
        $dupKey = 'target-duplikat:'.$userId.':'.sha1(json_encode([
            $data['id_dompet'],
            $data['nama_target'],
            $data['deskripsi'] ?? null,
            bcadd((string) $data['nominal_target'], '0', 2),
            $data['initial_savings'] ?? null,
        ]));

        if (! $duplicateGuard->reserve($dupKey, self::DUPLICATE_GUARD_SECONDS)) {
            throw ValidationException::withMessages([
                'nama_target' => 'Target tabungan ini baru saja dicatat — hanya satu yang tersimpan. '
                    .'Jika memang ingin membuat target serupa, ubah sedikit nama atau nominalnya, '
                    .'atau tunggu '.self::DUPLICATE_GUARD_SECONDS.' detik.',
            ]);
        }

        try {
            $wallet = Dompet::where('id_user', Auth::id())->findOrFail($data['id_dompet']);
            $nominalTarget = CurrencyService::toBaseExact((string) $data['nominal_target']);
            $initialSavings = isset($data['initial_savings'])
                ? CurrencyService::toBaseExact((string) $data['initial_savings'])
                : (string) $wallet->saldo;

            if (
                ! CurrencyService::baseAmountWithinStorageLimit($nominalTarget)
                || ! CurrencyService::baseAmountWithinStorageLimit($initialSavings)
            ) {
                throw ValidationException::withMessages([
                    'nominal_target' => 'Nominal setelah konversi melebihi batas nominal yang didukung.',
                ]);
            }

            if (bccomp($initialSavings, $nominalTarget, 2) > 0 || bccomp($initialSavings, (string) $wallet->saldo, 2) > 0) {
                throw ValidationException::withMessages([
                    'initial_savings' => 'Initial savings tidak boleh melebihi saldo wallet atau target.',
                ]);
            }

            $target = TargetTabungan::create([
                'id_dompet' => $wallet->id_dompet,
                'nama_target' => $data['nama_target'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'nominal_target' => $nominalTarget,
                'nominal_terkumpul' => $initialSavings,
                'saldo_awal_dompet' => $wallet->saldo,
                'id_user' => Auth::id(),
                'status' => bccomp($initialSavings, $nominalTarget, 2) >= 0 ? 'tercapai' : 'belum_tercapai',
            ]);

            if ($target->status === 'tercapai') {
                $notifikasiService->targetTercapai($target);
            }
        } catch (Throwable $e) {
            /* Proses gagal — buka lagi pintu retry-nya. */
            $duplicateGuard->release($dupKey);
            throw $e;
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $target->id_target]);
        }

        return redirect()->route('target-tabungan.index');
    }

    public function show(TargetTabungan $targetTabungan): View
    {
        $targetTabungan = $this->ownedTarget($targetTabungan);

        return view('target-tabungan.show', ['target' => $targetTabungan]);
    }

    public function edit(TargetTabungan $targetTabungan): View
    {
        $targetTabungan = $this->ownedTarget($targetTabungan);

        return view('target-tabungan.edit', [
            'target' => $targetTabungan,
            'dompet' => Dompet::where('id_user', Auth::id())->orderBy('nama_dompet')->get(),
        ]);
    }

    public function update(UpdateTargetTabunganRequest $request, TargetTabungan $targetTabungan): RedirectResponse
    {
        $targetTabungan = $this->ownedTarget($targetTabungan);
        $data = $request->validated();
        $wallet = Dompet::where('id_user', Auth::id())->findOrFail($data['id_dompet']);
        $nominalTarget = CurrencyService::toBaseExact((string) $data['nominal_target']);
        if (! CurrencyService::baseAmountWithinStorageLimit($nominalTarget)) {
            throw ValidationException::withMessages([
                'nominal_target' => 'Nominal setelah konversi melebihi batas nominal yang didukung.',
            ]);
        }
        $updates = [
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => $data['nama_target'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'nominal_target' => $nominalTarget,
        ];
        $walletChanged = (int) $wallet->id_dompet !== (int) $targetTabungan->id_dompet;

        if (($data['initial_savings'] ?? null) !== null || $walletChanged) {
            $initialSavings = isset($data['initial_savings'])
                ? CurrencyService::toBaseExact((string) $data['initial_savings'])
                : (string) $targetTabungan->jumlah_terkumpul;

            if (! isset($data['initial_savings'])) {
                foreach ([(string) $wallet->saldo, $nominalTarget] as $upperBound) {
                    if (bccomp($initialSavings, $upperBound, 2) > 0) {
                        $initialSavings = $upperBound;
                    }
                }
            }

            if (
                ! CurrencyService::baseAmountWithinStorageLimit($initialSavings)
                || bccomp($initialSavings, $nominalTarget, 2) > 0
                || bccomp($initialSavings, (string) $wallet->saldo, 2) > 0
            ) {
                throw ValidationException::withMessages([
                    'initial_savings' => 'Initial savings tidak boleh melebihi saldo wallet atau target.',
                ]);
            }

            $updates['nominal_terkumpul'] = $initialSavings;
            $updates['saldo_awal_dompet'] = (string) $wallet->saldo;
        }

        $targetTabungan->update($updates);

        return redirect()->route('target-tabungan.index');
    }

    public function destroy(TargetTabungan $targetTabungan): RedirectResponse|JsonResponse
    {
        $this->ownedTarget($targetTabungan)->delete();

        if (request()->expectsJson() || request()->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()->route('target-tabungan.index');
    }

    private function ownedTarget(TargetTabungan $target): TargetTabungan
    {
        abort_unless($target->id_user === (int) Auth::id(), 404);

        return $target->load('dompet');
    }
}
