<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTargetTabunganRequest;
use App\Http\Requests\UpdateTargetTabunganRequest;
use App\Models\Dompet;
use App\Models\TargetTabungan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TargetTabunganController extends Controller
{
    public function index(): View
    {
        return view('target-tabungan.index', [
            'targets' => TargetTabungan::with('dompet')
                ->where('id_user', Auth::id())
                ->latest('id_target')
                ->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('target-tabungan.create', [
            'dompet' => Dompet::where('id_user', Auth::id())->orderBy('nama_dompet')->get(),
        ]);
    }

    public function store(StoreTargetTabunganRequest $request): RedirectResponse
    {
        TargetTabungan::create([
            ...$request->validated(),
            'id_user' => Auth::id(),
            'status' => 'belum_tercapai',
        ]);

        return redirect()->route('target-tabungan.index');
    }

    public function show(TargetTabungan $targetTabungan): View
    {
        $targetTabungan = $this->ownedTarget($targetTabungan);

        return view('target-tabungan.show', ['target' => $targetTabungan]);
    }

    public function edit(TargetTabungan $targetTabungan): View
    {
        $targetTabungan = $this->ownedTarget($targetTabungan);

        return view('target-tabungan.edit', [
            'target' => $targetTabungan,
            'dompet' => Dompet::where('id_user', Auth::id())->orderBy('nama_dompet')->get(),
        ]);
    }

    public function update(UpdateTargetTabunganRequest $request, TargetTabungan $targetTabungan): RedirectResponse
    {
        $targetTabungan = $this->ownedTarget($targetTabungan);
        $targetTabungan->update($request->validated());

        return redirect()->route('target-tabungan.index');
    }

    public function destroy(TargetTabungan $targetTabungan): RedirectResponse
    {
        $this->ownedTarget($targetTabungan)->delete();

        return redirect()->route('target-tabungan.index');
    }

    private function ownedTarget(TargetTabungan $target): TargetTabungan
    {
        abort_unless($target->id_user === (int) Auth::id(), 404);

        return $target->load('dompet');
    }
}
