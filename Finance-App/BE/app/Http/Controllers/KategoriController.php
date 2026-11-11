<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKategoriRequest;
use App\Http\Requests\UpdateKategoriRequest;
use App\Models\Kategori;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KategoriController extends Controller
{
    public function frontendIndex(Request $request): View|JsonResponse
    {
        $icons = ['makanan' => 'utensils', 'transport' => 'car', 'hiburan' => 'film', 'tagihan' => 'landmark', 'gaji' => 'landmark'];

        $categories = Kategori::withCount('transaksi')
            ->where('id_user', Auth::id())
            ->orderBy('nama_kategori')
            ->get()
            ->map(fn (Kategori $category): array => [
                'id' => $category->id_kategori,
                'name' => $category->nama_kategori,
                'type' => $category->jenis === 'pemasukan' ? 'income' : 'expense',
                'icon' => $category->icon ?: ($icons[strtolower($category->nama_kategori)] ?? 'landmark'),
                'count' => $category->transaksi_count,
            ]);

        if ($request->expectsJson()) {
            return response()->json($categories);
        }

        return view('transactions.categories', [
            'categories' => $categories,
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

    public function store(StoreKategoriRequest $request): RedirectResponse|JsonResponse
    {
        $kategori = Kategori::create([...$request->validated(), 'id_user' => Auth::id()]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Kategori berhasil ditambahkan.', 'kategori' => $kategori], 201);
        }

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

    public function update(UpdateKategoriRequest $request, Kategori $kategori): RedirectResponse|JsonResponse
    {
        abort_unless($kategori->id_user === Auth::id(), 404);
        $kategori->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Kategori berhasil diubah.']);
        }

        return redirect()->route('kategori.index');
    }

    public function destroy(Kategori $kategori): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless($kategori->id_user === Auth::id(), 404);
        
        // Cannot delete category if it has transactions
        if ($kategori->transaksi()->count() > 0) {
            if (request()->expectsJson() || request()->wantsJson()) {
                return response()->json(['message' => 'Category cannot be deleted because it has transactions. You can only edit it.'], 422);
            }
            return redirect()->route('kategori.index')->withErrors(['message' => 'Category cannot be deleted because it has transactions. You can only edit it.']);
        }
        
        $kategori->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Kategori berhasil dihapus.']);
        }

        return redirect()->route('kategori.index');
    }
}
