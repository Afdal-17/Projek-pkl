<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Http\Requests\StoreDompetRequest;
use App\Http\Requests\UpdateDompetRequest;
use App\Models\Dompet;
use Illuminate\Support\Facades\Auth;
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

    public function destroy(Dompet $dompet): RedirectResponse
    {
        abort_unless($dompet->id_user === Auth::id(), 404);
        $dompet->delete();
        return redirect()->route('dompet.index');
    }
}
