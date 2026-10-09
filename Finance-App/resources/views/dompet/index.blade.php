@extends('layouts.main')

@section('title', __('dompet.title'))

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold">{{ __('dompet.title') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ __('dompet.subtitle') }}</p>
        </div>
        <a href="{{ route('dompet.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-dark px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
            <x-icon name="plus" size="h-4 w-4" /> {{ __('dompet.add_wallet') }}
        </a>
    </div>

    <x-card class="mt-8 overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="border-b border-line bg-page text-muted">
                <tr>
                    <th class="px-6 py-3 font-semibold">{{ __('dompet.wallet_name') }}</th>
                    <th class="px-6 py-3 font-semibold">{{ __('dompet.balance') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('dompet.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($dompet as $item)
                    <tr>
                        <td class="px-6 py-4">
                            <p class="font-semibold">{{ $item->nama_dompet }}</p>
                            <p class="text-xs text-muted">{{ $item->deskripsi ?: __('dompet.no_description') }}</p>
                        </td>
                        <td class="px-6 py-4">Rp {{ number_format((float) $item->saldo, 2, ',', '.') }}</td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('dompet.show', $item) }}" class="text-brand hover:underline">{{ __('dompet.detail') }}</a>
                            <a href="{{ route('dompet.edit', $item) }}" class="text-brand hover:underline">{{ __('dompet.edit') }}</a>
                            <form class="inline" method="POST" action="{{ route('dompet.destroy', $item) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-expense hover:underline" x-on:click="if (!confirm(@js(__('dompet.delete_confirm')))) { $event.preventDefault(); }">{{ __('dompet.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-6 py-8 text-center text-muted">{{ __('dompet.no_wallets') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-line px-6 py-4">{{ $dompet->links() }}</div>
    </x-card>
@endsection
