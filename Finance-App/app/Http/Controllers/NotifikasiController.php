<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Models\TargetTabungan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotifikasiController extends Controller
{
    public function frontendIndex(): View
    {
        $userTargets = TargetTabungan::where('id_user', Auth::id())
            ->with('dompet')
            ->get();

        $notifications = Notifikasi::where('id_user', Auth::id())
            ->latest('id_notifikasi')
            ->get()
	            ->map(function (Notifikasi $notification) use ($userTargets): array {
                $isTarget = $notification->tipe === 'target'
                    || $notification->display_type === 'saving';

                $isTransfer = str_starts_with($notification->pesan ?? '', 'Transfer ')
                    || str_starts_with($notification->pesan ?? '', 'Anda di transfer oleh ');

                $type = $isTarget ? 'saving' : ($isTransfer ? 'transfer' : 'transaction');

                $title = $isTarget
                    ? 'Savings goal reached'
                    : ($isTransfer ? 'Transfer success!' : 'Transaction update');

                $goal = null;

                if ($isTarget) {
                    $targetName = null;

                    if (preg_match('/^Target (.+?) (?:telah tercapai|has been reached)\.?$/ui', $notification->pesan ?? '', $matches)) {
                        $targetName = trim($matches[1]);
                    }

                    $matchedTarget = $targetName
                        ? $userTargets->first(fn ($t) => strcasecmp($t->nama_target, $targetName) === 0)
                        : $userTargets->first();

                    if (!$matchedTarget && $targetName) {
                        $matchedTarget = $userTargets->first(
                            fn ($t) => stripos($t->nama_target, $targetName) !== false
                                || stripos($targetName, $t->nama_target) !== false
                        );
                    }

                    if (!$matchedTarget) {
                        $matchedTarget = $userTargets->first(
                            fn ($t) => $t->tercapai || $t->status === 'tercapai'
                        ) ?: $userTargets->first();
                    }

                    if ($matchedTarget) {
                        $targetNominal = (string) $matchedTarget->nominal_target;
                        $savedNominal = bccomp((string) $matchedTarget->jumlah_terkumpul, $targetNominal, 2) > 0
                            ? (string) $matchedTarget->jumlah_terkumpul
                            : $targetNominal;

                        $goal = [
                            'name' => $matchedTarget->nama_target,
                            'note' => $matchedTarget->deskripsi ?: 'Savings target',
                            'saved' => $savedNominal,
                            'target' => bccomp($targetNominal, '0.00', 2) > 0 ? $targetNominal : $savedNominal,
                        ];
                    } elseif ($targetName) {
                        $goal = [
                            'name' => $targetName,
                            'note' => 'Savings target',
                            'saved' => 100,
                            'target' => 100,
                        ];
                    }
                }

                return [
                    'id' => $notification->id_notifikasi,
                    'type' => $type,
                    'title' => $title,
                    'text' => $notification->pesanTampil(),
                    'scope' => $notification->tanggal->diffForHumans(),
                    'read' => $notification->sudah_dibaca,
                    'goal' => $goal,
                ];
            });
        return view('notifications', [
            'notifications' => $notifications,
            'readIds' => $notifications->where('read', true)->pluck('id')->values(),
        ]);
    }

    public function index(): View
    {
        return view('notifikasi.index', [
            'notifikasi' => Notifikasi::where('id_user', Auth::id())
                ->latest('id_notifikasi')
                ->paginate(15),
        ]);
    }

    public function read(Notifikasi $notifikasi): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->owned($notifikasi)->update(['sudah_dibaca' => true]);

        if (request()->expectsJson() || request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function readAll(): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        Notifikasi::where('id_user', Auth::id())
            ->where('sudah_dibaca', false)
            ->update(['sudah_dibaca' => true]);

        if (request()->expectsJson() || request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    private function owned(Notifikasi $notifikasi): Notifikasi
    {
        abort_unless($notifikasi->id_user === (int) Auth::id(), 404);

        return $notifikasi;
    }
}
