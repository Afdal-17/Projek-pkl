@extends('layouts.main')

@section('title', $dompet->nama_dompet)

@section('content')
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold">{{ $dompet->nama_dompet }}</h1>
        <x-card class="mt-8 p-6 space-y-4">
            <div>
                <span class="text-sm text-muted">{{ __('dompet.balance') }}</span>
                <p class="text-2xl font-semibold">Rp {{ number_format((float) $dompet->saldo, 2, ',', '.') }}</p>
            </div>
            <p class="text-muted">{{ $dompet->deskripsi ?: __('dompet.no_description') }}</p>
            <div class="flex justify-end gap-3 pt-4 border-t border-line">
                <a href="{{ route('dompet.index') }}" class="px-4 py-2 text-muted hover:underline">{{ __('dompet.back') }}</a>
                <a href="{{ route('dompet.edit', $dompet) }}" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:opacity-90">{{ __('dompet.edit') }}</a>
            </div>
        </x-card>
    </div>
@endsection
