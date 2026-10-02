<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use App\Models\Transfer;
use App\Services\NotifikasiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function index(): View
    {
        $userId = (int) Auth::id();

        return view('transfer.index', [
            'transfer' => Transfer::with(['dompetAsal.user', 'dompetTujuan.user'])
                ->where(function (Builder $query) use ($userId): void {
                    $query->where('id_user', $userId)
                        ->orWhereHas('dompetTujuan', fn (Builder $wallet) => $wallet->where('id_user', $userId));
                })
                ->latest('tanggal_transfer')
                ->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('transfer.create', [
            'dompet' => Dompet::with('user')->orderBy('nama_dompet')->get(),
            'dompetPengirim' => Dompet::where('id_user', Auth::id())->orderBy('nama_dompet')->get(),
        ]);
    }

    public function store(StoreTransferRequest $request, NotifikasiService $notifikasiService): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) Auth::id();

        DB::transaction(function () use ($data, $userId, $notifikasiService): void {
            $wallets = Dompet::whereIn('id_dompet', [$data['id_dompet_asal'], $data['id_dompet_tujuan']])
                ->orderBy('id_dompet')
                ->lockForUpdate()
                ->get()
                ->keyBy('id_dompet');
            $source = $wallets->get((int) $data['id_dompet_asal']);
            $target = $wallets->get((int) $data['id_dompet_tujuan']);

            abort_unless($source?->id_user === $userId && $target !== null, 403);

            $amount = (float) $data['jumlah'];
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
