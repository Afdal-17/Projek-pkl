<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DompetController extends Controller
{
    public function index(): View
    {
        return view('dashboard', ['title' => 'Dompet']);
    }

    public function create(): View
    {
        return view('dashboard', ['title' => 'Tambah Dompet']);
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('dompet.index');
    }

    public function show(string $id): View
    {
        return view('dashboard', ['title' => 'Detail Dompet: '.$id]);
    }

    public function edit(string $id): View
    {
        return view('dashboard', ['title' => 'Edit Dompet: '.$id]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        return redirect()->route('dompet.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        return redirect()->route('dompet.index');
    }
}
