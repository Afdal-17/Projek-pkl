<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function index(): View
    {
        return view('dashboard', ['title' => 'Transfer']);
    }

    public function create(): View
    {
        return view('dashboard', ['title' => 'Tambah Transfer']);
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('transfer.index');
    }

    public function show(string $id): View
    {
        return view('dashboard', ['title' => 'Detail Transfer: '.$id]);
    }

    public function edit(string $id): View
    {
        return view('dashboard', ['title' => 'Edit Transfer: '.$id]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        return redirect()->route('transfer.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        return redirect()->route('transfer.index');
    }
}
