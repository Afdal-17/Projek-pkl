<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use App\Models\Transfer;
use App\Models\User;
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

    public function frontendRecipients(): \Illuminate\Http\JsonResponse
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

        $wallet = $recipient->dompet->first();

        return response()->json([
            'id_dompet_tujuan' => $wallet->id_dompet,
            'penerima' => $recipient->nama,
            'dompet' => $wallet->nama_dompet,
        ]);
    }

    public function searchRecipients(): \Illuminate\Http\JsonResponse
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
            $users->map(fn (User $user): array => [
                'id_user' => $user->id_user,
                'nama' => $user->nama,
                'email' => $user->email,
                'is_banned' => $user->isBanned(),
                'has_wallet' => $user->dompet->isNotEmpty(),
                'wallets' => $user->dompet->map(fn (Dompet $wallet): array => [
                    'id' => $wallet->id_dompet,
                    'name' => $wallet->nama_dompet,
                ])->values(),
                'id_dompet_tujuan' => $user->dompet->first()?->id_dompet,
                'dompet' => $user->dompet->first()?->nama_dompet,
            ])->values()
        );
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
