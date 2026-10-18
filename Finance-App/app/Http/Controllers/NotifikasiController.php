<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotifikasiController extends Controller
{
    public function frontendIndex(): View
    {
        $notifications = Notifikasi::where('id_user', Auth::id())
            ->latest('id_notifikasi')
            ->get()
            ->map(fn (Notifikasi $notification): array => [
                'id' => $notification->id_notifikasi,
                'type' => $notification->display_type,
                'title' => $notification->display_title,
                'text' => $notification->formatted_pesan,
                'scope' => $notification->tanggal->diffForHumans(),
                'read' => $notification->sudah_dibaca,
            ]);

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