<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTargetTabunganRequest;
use App\Http\Requests\UpdateTargetTabunganRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TargetTabunganController extends Controller
{
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
                'saved' => min((float) $target->jumlah_terkumpul, (float) $target->nominal_target),
                'target' => (float) $target->nominal_target,
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
                    'balance' => (float) $wallet->saldo,
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

    public function store(StoreTargetTabunganRequest $request, NotifikasiService $notifikasiService): RedirectResponse
    {
        $data = $request->validated();
        $wallet = Dompet::where('id_user', Auth::id())->findOrFail($data['id_dompet']);
        $initialSavings = $data['initial_savings'] ?? (float) $wallet->saldo;

        if ((float) $initialSavings > (float) $data['nominal_target'] || (float) $initialSavings > (float) $wallet->saldo) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'initial_savings' => 'Initial savings tidak boleh melebihi saldo wallet atau target.',
            ]);
        }

        $target = TargetTabungan::create([
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => $data['nama_target'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'nominal_target' => $data['nominal_target'],
            'nominal_terkumpul' => (float) $initialSavings,
            'saldo_awal_dompet' => $wallet->saldo,
            'id_user' => Auth::id(),
            'status' => (float) $initialSavings >= (float) $data['nominal_target'] ? 'tercapai' : 'belum_tercapai',
        ]);

        if ($target->status === 'tercapai') {
            $notifikasiService->targetTercapai($target);
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
        $updates = [
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => $data['nama_target'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'nominal_target' => $data['nominal_target'],
        ];
        $walletChanged = (int) $wallet->id_dompet !== (int) $targetTabungan->id_dompet;

        if (($data['initial_savings'] ?? null) !== null || $walletChanged) {
            $initialSavings = $data['initial_savings'] ?? min(
                (float) $targetTabungan->jumlah_terkumpul,
                (float) $wallet->saldo,
                (float) $data['nominal_target']
            );

            if ((float) $initialSavings > (float) $data['nominal_target'] || (float) $initialSavings > (float) $wallet->saldo) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'initial_savings' => 'Initial savings tidak boleh melebihi saldo wallet atau target.',
                ]);
            }

            $updates['nominal_terkumpul'] = (float) $initialSavings;
            $updates['saldo_awal_dompet'] = (float) $wallet->saldo;
        }

        $targetTabungan->update($updates);

        return redirect()->route('target-tabungan.index');
    }

    public function destroy(TargetTabungan $targetTabungan): RedirectResponse
    {
        $this->ownedTarget($targetTabungan)->delete();

        return redirect()->route('target-tabungan.index');
    }

    private function ownedTarget(TargetTabungan $target): TargetTabungan
    {
        abort_unless($target->id_user === (int) Auth::id(), 404);

        return $target->load('dompet');
    }
}
