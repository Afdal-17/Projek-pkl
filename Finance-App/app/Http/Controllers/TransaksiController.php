<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransaksiController extends Controller
{
    public function index(): View
    {
        return view('dashboard', ['title' => 'Transaksi']);
    }

    public function create(): View
    {
        return view('dashboard', ['title' => 'Tambah Transaksi']);
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('transaksi.index');
    }

    public function show(string $id): View
    {
        return view('dashboard', ['title' => 'Detail Transaksi: '.$id]);
    }

    public function edit(string $id): View
    {
        return view('dashboard', ['title' => 'Edit Transaksi: '.$id]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        return redirect()->route('transaksi.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        return redirect()->route('transaksi.index');
    }
}
