@extends('layouts.app')

@section('title', 'Saving')

@section('content')

@php
    // DATA DUMMY: nanti diganti data dari backend
    $targets = [
        ['id' => 1, 'name' => 'Emergency Fund', 'note' => 'Financial safety net', 'saved' => 9000000,  'target' => 12000000],
        ['id' => 2, 'name' => 'Japan Vacation', 'note' => 'March 2027',           'saved' => 7000000,  'target' => 12500000],
        ['id' => 3, 'name' => 'New Laptop',     'note' => 'Work equipment',       'saved' => 6000000,  'target' => 15000000],
        ['id' => 4, 'name' => 'Home Deposit',   'note' => 'Long-term goal',       'saved' => 12000000, 'target' => 50000000],
    ];
@endphp

<div
    x-data="{
        targets: [
            ...@js($targets),
            ...JSON.parse(localStorage.getItem('savingTargets') || '[]'),
        ],

        rp(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        },

        pct(t) {
            if (!t.target) return 0;
            return Math.min(100, Math.round(t.saved / t.target * 100));
        },

        get totalSaved() {
            return this.targets.reduce((sum, t) => sum + Number(t.saved), 0);
        },

        get totalTarget() {
            return this.targets.reduce((sum, t) => sum + Number(t.target), 0);
        },

        get overallPct() {
            if (!this.totalTarget) return 0;
            return Math.round(this.totalSaved / this.totalTarget * 100);
        },

        get nextMilestone() {
            const open = this.targets
                .filter(t => t.saved < t.target)
                .sort((a, b) => (a.target - a.saved) - (b.target - b.saved));

            return open[0] ?? null;
        },

        removeTarget(id) {
            if (!confirm('Delete this saving target?')) return;

            this.targets = this.targets.filter(t => t.id !== id);

            const stored = JSON.parse(localStorage.getItem('savingTargets') || '[]')
                .filter(t => t.id !== id);

            localStorage.setItem('savingTargets', JSON.stringify(stored));
        }
    }"
>

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold">Saving</h1>
            <p class="mt-1 text-sm text-muted">Set targets and follow your progress over time.</p>
        </div>

        <x-button href="/saving/create">
            <x-icon name="plus" size="h-4 w-4" /> Add Saving Target
        </x-button>
    </div>


    {{-- Tiga kartu ringkasan --}}
    <div class="mt-8 grid gap-6 md:grid-cols-3">

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">Total saved</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-income-soft text-income">
                    <x-icon name="dollar" />
                </span>
            </div>
            <p class="mt-4 text-2xl font-bold" x-text="rp(totalSaved)"></p>
            <p class="mt-2 text-xs text-income">+Rp 2.500.000 this month</p>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">Combined target</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-soft text-brand">
                    <x-icon name="target" />
                </span>
            </div>
            <p class="mt-4 text-2xl font-bold" x-text="rp(totalTarget)"></p>
            <p class="mt-2 text-xs text-muted"
               x-text="overallPct + '% funded across ' + targets.length + ' targets'"></p>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">Next milestone</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-warn-soft text-warn">
                    <x-icon name="history" />
                </span>
            </div>
            <p class="mt-4 text-2xl font-bold"
               x-text="nextMilestone ? rp(nextMilestone.target - nextMilestone.saved) : 'Rp 0'"></p>
            <p class="mt-2 text-xs text-muted"
               x-text="nextMilestone ? 'To complete ' + nextMilestone.name : 'All targets completed'"></p>
        </x-card>

    </div>


    {{-- Tabel target --}}
    <x-card class="mt-6 overflow-hidden">

        {{-- Kepala tabel --}}
        <div class="grid grid-cols-[1.4fr_3fr_1.4fr_1fr] gap-6 border-b border-line px-6 py-4 text-[11px] font-semibold uppercase tracking-wide text-muted">
            <span>Target name</span>
            <span>Progress</span>
            <span>Amount</span>
            <span>Action</span>
        </div>

        {{-- Baris --}}
        <template x-for="t in targets" :key="t.id">

            <div class="grid grid-cols-[1.4fr_3fr_1.4fr_1fr] items-center gap-6 border-b border-line px-6 py-5">

                {{-- Nama --}}
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand">
                        <x-icon name="target" size="h-4 w-4" />
                    </span>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold" x-text="t.name"></p>
                        <p class="text-xs text-muted" x-text="t.note"></p>
                    </div>
                </div>

                {{-- Progress --}}
                <div>
                    <div class="flex items-center justify-between text-xs text-muted">
                        <span x-text="rp(t.saved) + ' saved'"></span>
                        <span class="rounded-full bg-brand-soft px-2.5 py-1 font-medium text-brand"
                              x-text="pct(t) + '%'"></span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-page">
                        <div class="h-2 rounded-full bg-brand" :style="'width: ' + pct(t) + '%'"></div>
                    </div>
                </div>

                {{-- Amount --}}
                <div class="leading-tight">
                    <p class="text-sm font-semibold" x-text="rp(t.saved)"></p>
                    <p class="text-xs text-muted" x-text="'of ' + rp(t.target)"></p>
                </div>

                {{-- Action --}}
                <div>
                    <x-button variant="danger" x-on:click="removeTarget(t.id)">
                        <x-icon name="trash" size="h-4 w-4" /> Delete
                    </x-button>
                </div>

            </div>

        </template>

        {{-- Kosong --}}
        <template x-if="targets.length === 0">
            <p class="px-6 py-10 text-center text-sm text-muted">
                No saving targets yet. Click "Add Saving Target" to create one.
            </p>
        </template>

        {{-- Footer --}}
        <div class="px-6 py-4 text-xs text-muted"
             x-text="targets.length + ' active targets · Keep going, you are making steady progress.'"></div>

    </x-card>

</div>

@endsection