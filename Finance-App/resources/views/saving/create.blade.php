@extends('layouts.main')

@section('title', 'Add Saving Target')

@section('content')

@php
    // DATA DUMMY: nanti diganti data dari backend
    $wallets = [
        ['name' => 'Cash'],
        ['name' => 'DANA'],
        ['name' => 'GoPay'],
    ];
@endphp

<div
    x-data="{
        name: '',
        note: '',
        wallet: 'DANA',
        target: '',
        initial: '',
        wallets: @js($wallets),

        rp(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(amount) || 0);
        },

        saveTarget() {
            const target = Number(this.target);
            const initial = Number(this.initial) || 0;

            if (!this.name.trim()) {
                alert('Name target harus diisi.');
                return;
            }

            if (!target || target <= 0) {
                alert('Saving target harus lebih dari 0.');
                return;
            }

            if (initial < 0 || initial > target) {
                alert('Initial savings tidak boleh melebihi target.');
                return;
            }

            const list = JSON.parse(localStorage.getItem('savingTargets') || '[]');

            list.push({
                id: Date.now(),
                name: this.name.trim(),
                note: this.note.trim(),
                wallet: this.wallet,
                saved: initial,
                target: target,
            });

            localStorage.setItem('savingTargets', JSON.stringify(list));

            window.location.href = '/saving';
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">Add Saving Target</h1>
        <p class="mt-1 text-sm text-muted">Set your own personal savings goal.</p>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1.2fr]">

        {{-- FORM --}}
        <x-card class="p-8">

            {{-- Name --}}
            <div>
                <label for="target_name" class="mb-2 block text-sm font-medium">Name Target</label>
                <input
                    id="target_name"
                    type="text"
                    x-model="name"
                    placeholder="e.g. New Laptop"
                    class="w-full rounded-lg border border-line bg-white px-3 py-3 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >
            </div>

            {{-- Description --}}
            <div class="mt-6">
                <label for="target_note" class="mb-2 block text-sm font-medium">Description</label>
                <textarea
                    id="target_note"
                    rows="4"
                    x-model="note"
                    placeholder="e.g. Work equipment"
                    class="w-full rounded-lg border border-line bg-white px-3 py-3 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                ></textarea>
            </div>

            {{-- Wallet --}}
            <div class="mt-6">
                <label for="target_wallet" class="mb-2 block text-sm font-medium">Wallet</label>

                <div class="relative">
                    <x-icon name="wallet" size="h-4 w-4"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />

                    <select
                        id="target_wallet"
                        x-model="wallet"
                        class="w-full appearance-none rounded-lg border border-line bg-white py-3 pl-9 pr-10 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >
                        <template x-for="item in wallets" :key="item.name">
                            <option :value="item.name" :selected="item.name === wallet" x-text="item.name"></option>
                        </template>
                    </select>

                    <x-icon name="chevron-down" size="h-4 w-4"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" />
                </div>
            </div>

            {{-- Saving target --}}
            <div class="mt-6">
                <label for="target_amount" class="mb-2 block text-sm font-medium">Saving Target</label>

                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-muted">Rp</span>
                    <input
                        id="target_amount"
                        type="number"
                        min="0"
                        x-model.number="target"
                        placeholder="0"
                        class="w-full rounded-lg border border-line bg-white py-3 pl-12 pr-4 text-2xl font-semibold placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >
                </div>
            </div>

            {{-- Initial savings --}}
            <div class="mt-6">
                <label for="initial_amount" class="mb-2 block text-sm font-medium">Initial Savings (Optional)</label>

                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-muted">Rp</span>
                    <input
                        id="initial_amount"
                        type="number"
                        min="0"
                        x-model.number="initial"
                        placeholder="0"
                        class="w-full rounded-lg border border-line bg-white py-3 pl-12 pr-4 text-2xl font-semibold placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >
                </div>
            </div>

            {{-- Tombol --}}
            <div class="mt-6 flex justify-end gap-3">
                <x-button href="/saving" variant="outline">Cancel</x-button>
                <x-button x-on:click="saveTarget()">Add target</x-button>
            </div>

        </x-card>


        {{-- SUMMARY --}}
        <div>
            <x-card class="p-6">

                <h2 class="text-lg font-semibold">Summary</h2>

                <div class="mt-5 space-y-4 text-sm">

                    <div class="flex items-center justify-between">
                        <span class="text-muted">Name Target</span>
                        <span class="rounded-full bg-expense-soft px-3 py-1 text-xs font-medium"
                              x-text="name || '-'"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">Wallet</span>
                        <span class="rounded-full bg-expense-soft px-3 py-1 text-xs font-medium"
                              x-text="wallet"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">Saving Target</span>
                        <span class="text-base font-bold" x-text="rp(target)"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">Initial Savings</span>
                        <span class="text-base font-bold" x-text="rp(initial)"></span>
                    </div>

                </div>

            </x-card>
        </div>

    </div>

</div>

@endsection