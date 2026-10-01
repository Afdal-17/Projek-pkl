<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKategoriRequest;
use App\Http\Requests\UpdateKategoriRequest;
use App\Models\Kategori;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KategoriController extends Controller
{
    public function index(): View
    {
        return view('kategori.index', ['kategori' => Kategori::where('id_user', Auth::id())->latest()->paginate(10)]);
    }

    public function create(): View
    {
        return view('kategori.create');
    }

    public function store(StoreKategoriRequest $request): RedirectResponse
    {
        Kategori::create([...$request->validated(), 'id_user' => Auth::id()]);
        return redirect()->route('kategori.index');
    }

    public function show(Kategori $kategori): View
    {
        abort_unless($kategori->id_user === Auth::id(), 404);
        return view('kategori.show', compact('kategori'));
    }

    public function edit(Kategori $kategori): View
    {
        abort_unless($kategori->id_user === Auth::id(), 404);
        return view('kategori.edit', compact('kategori'));
    }

    public function update(UpdateKategoriRequest $request, Kategori $kategori): RedirectResponse
    {
        abort_unless($kategori->id_user === Auth::id(), 404);
        $kategori->update($request->validated());
        return redirect()->route('kategori.index');
    }

    public function destroy(Kategori $kategori): RedirectResponse
    {
        abort_unless($kategori->id_user === Auth::id(), 404);
        $kategori->delete();
        return redirect()->route('kategori.index');
    }
}
