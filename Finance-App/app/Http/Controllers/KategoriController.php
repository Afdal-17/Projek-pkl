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
    public function frontendIndex(): View
    {
        $icons = ['makanan' => 'utensils', 'transport' => 'car', 'hiburan' => 'film', 'tagihan' => 'landmark', 'gaji' => 'landmark'];

        return view('transactions.categories', [
            'categories' => Kategori::withCount('transaksi')
                ->where('id_user', Auth::id())
                ->orderBy('nama_kategori')
                ->get()
                ->map(fn (Kategori $category): array => [
                    'id' => $category->id_kategori,
                    'name' => $category->nama_kategori,
                    'type' => $category->jenis === 'pemasukan' ? 'income' : 'expense',
                    'icon' => $icons[strtolower($category->nama_kategori)] ?? 'landmark',
                    'count' => $category->transaksi_count,
                ]),
        ]);
    }

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
