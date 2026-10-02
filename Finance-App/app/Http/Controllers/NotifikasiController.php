<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotifikasiController extends Controller
{
    public function index(): View
    {
        return view('notifikasi.index', [
            'notifikasi' => Notifikasi::where('id_user', Auth::id())
                ->latest('tanggal')
                ->paginate(15),
        ]);
    }

    public function read(Notifikasi $notifikasi): RedirectResponse
    {
        $this->owned($notifikasi)->update(['sudah_dibaca' => true]);

        return back();
    }

    public function readAll(): RedirectResponse
    {
        Notifikasi::where('id_user', Auth::id())
            ->where('sudah_dibaca', false)
            ->update(['sudah_dibaca' => true]);

        return back();
    }

    private function owned(Notifikasi $notifikasi): Notifikasi
    {
        abort_unless($notifikasi->id_user === (int) Auth::id(), 404);

        return $notifikasi;
    }
}