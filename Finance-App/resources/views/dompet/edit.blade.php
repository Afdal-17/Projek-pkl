@extends('layouts.main')

@section('title', __('dompet.edit'))

@section('content')
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold">{{ __('dompet.edit') }}</h1>
        <x-card class="mt-8 p-6">
            <form method="POST" action="{{ route('dompet.update', $dompet) }}" class="space-y-5">
                @csrf @method('PUT')
                @include('dompet.form')
                <div class="flex justify-end gap-3">
                    <a href="{{ route('dompet.index') }}" class="px-4 py-2 text-muted hover:underline">{{ __('dompet.cancel') }}</a>
                    <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:opacity-90">{{ __('dompet.save') }}</button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
