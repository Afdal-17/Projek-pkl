@extends('layouts.main')

@section('title', __('saving.title'))

@section('content')

<div
    x-data="{
        targets: @js($targets),
        csrfToken: @js(csrf_token()),
        deleteUrl: @js(route('saving.destroy', ['targetTabungan' => '__ID__'])),

        rp(amount) {
            return window.financeMoney.format(amount);
        },

        pct(t) {
            if (window.financeMoney.compare(t.target, '0') <= 0) return 0;
            return Math.min(100, window.financeMoney.percent(t.saved, t.target));
        },

        get totalSaved() {
            return this.targets.reduce((sum, t) => window.financeMoney.add(sum, t.saved), '0.00');
        },

        get totalTarget() {
            return this.targets.reduce((sum, t) => window.financeMoney.add(sum, t.target), '0.00');
        },

        get overallPct() {
            return window.financeMoney.percent(this.totalSaved, this.totalTarget);
        },

        get nextMilestone() {
            const open = this.targets
                .filter(t => window.financeMoney.compare(t.saved, t.target) < 0)
                .sort((a, b) => window.financeMoney.compare(
                    window.financeMoney.subtract(a.target, a.saved),
                    window.financeMoney.subtract(b.target, b.saved)
                ));

            return open[0] ?? null;
        },

        isDeleting: false,
        deleteError: '',
        confirmDialog: {
            open: false,
            targetId: null,
            targetName: '',
        },

        promptDelete(targetOrId) {
            const target = typeof targetOrId === 'object' && targetOrId !== null
                ? targetOrId
                : this.targets.find(t => t.id === targetOrId);

            if (!target) return;

            this.confirmDialog.targetId = target.id;
            this.confirmDialog.targetName = target.name || '';
            this.deleteError = '';
            this.confirmDialog.open = true;
        },

        closeConfirm() {
            if (this.isDeleting) return;
            this.confirmDialog.open = false;
            this.confirmDialog.targetId = null;
            this.confirmDialog.targetName = '';
            this.deleteError = '';
        },

        async executeDelete() {
            if (this.isDeleting || !this.confirmDialog.targetId) return;

            this.isDeleting = true;
            try {
                const response = await fetch(this.deleteUrl.replace('__ID__', this.confirmDialog.targetId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                });

                if (response.ok) {
                    window.location.reload();
                    return;
                }

                let result = {};

                try {
                    result = await response.json();
                } catch (error) {
                    // Keep a useful message if the server did not return JSON.
                }

                this.deleteError = Object.values(result.errors ?? {}).flat()[0]
                    ?? result.message
                    ?? 'Target tabungan gagal dihapus. Silakan coba lagi.';
            } catch (err) {
                this.deleteError = 'Koneksi gagal. Target tabungan belum dihapus; silakan coba lagi.';
            } finally {
                this.isDeleting = false;
            }
        },

        removeTarget(targetOrId) {
            this.promptDelete(targetOrId);
        }
    }"
>

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">{{ __('saving.title') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ __('saving.subtitle') }}</p>
        </div>

        <x-button href="/saving/create">
            <x-icon name="plus" size="h-4 w-4" /> {{ __('saving.add_target') }}
        </x-button>
    </div>


    {{-- Tiga kartu ringkasan --}}
    <div class="mt-8 grid gap-6 md:grid-cols-3">

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">{{ __('saving.total_saved') }}</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-income-soft text-income">
                    <x-icon name="dollar" />
                </span>
            </div>
            <p class="mt-4 text-2xl font-bold break-words" x-text="rp(totalSaved)"></p>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">{{ __('saving.combined_target') }}</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-soft text-brand">
                    <x-icon name="target" />
                </span>
            </div>
            <p class="mt-4 text-2xl font-bold break-words" x-text="rp(totalTarget)"></p>
            <p class="mt-2 text-xs text-muted"
              x-text="overallPct + '% ' + @js(__('saving.funded_across')) + ' ' + targets.length + ' ' + (targets.length === 1 ? @js(__('saving.target')) : @js(__('saving.targets')))"></p>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">{{ __('saving.next_milestone') }}</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-warn-soft text-warn">
                    <x-icon name="history" />
                </span>
            </div>
            <p class="mt-4 text-2xl font-bold"
               x-text="nextMilestone ? rp(window.financeMoney.subtract(nextMilestone.target, nextMilestone.saved)) : rp('0.00')"></p>
            <p class="mt-2 text-xs text-muted"
               x-text="nextMilestone ? @js(__('saving.to_complete')) + ' ' + nextMilestone.name : @js(__('saving.all_completed'))"></p>
        </x-card>

    </div>


    {{-- Tabel / List target --}}
    <x-card class="mt-6 overflow-hidden">

        {{-- ========================================================
            TAMPILAN MOBILE (< md): Card List tanpa scroll horizontal
        ========================================================= --}}
        <div class="md:hidden divide-y divide-line">
            <template x-for="t in targets" :key="t.id">
                <div class="p-4 sm:p-5 space-y-3.5">
                    {{-- Header item: Icon, Nama Target, Note & Wallet, Tombol Delete --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">
                                <x-icon name="target" size="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-dark" x-text="t.name"></p>
                                <p class="truncate text-xs text-muted" x-show="t.note && t.note !== t.name" x-text="t.note"></p>
                                <p class="mt-0.5 text-xs text-muted">
                                    {{ __('saving.from_wallet') }} <span class="font-medium text-dark" x-text="t.wallet"></span>
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            x-on:click="promptDelete(t)"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-expense hover:bg-expense-soft transition"
                            title="{{ __('saving.delete') }}"
                        >
                            <x-icon name="trash" size="h-4 w-4" />
                        </button>
                    </div>

                    {{-- Progress Bar & Nominal --}}
                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-dark tabular-nums" x-text="rp(t.saved)"></span>
                            <span class="text-muted tabular-nums" x-text="@js(__('saving.of')) + ' ' + rp(t.target)"></span>
                        </div>

                        <div class="h-2 w-full rounded-full bg-page overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="pct(t) >= 100 ? 'bg-income' : 'bg-brand'"
                                :style="'width: ' + pct(t) + '%'"
                            ></div>
                        </div>

                        <div class="flex items-center justify-between pt-0.5 text-[11px]">
                            <span class="text-muted" x-text="pct(t) >= 100 ? @js(__('saving.all_completed')) : @js(__('saving.saved_suffix'))"></span>
                            <span
                                class="rounded-full px-2 py-0.5 font-semibold tabular-nums"
                                :class="pct(t) >= 100 ? 'bg-income-soft text-income' : 'bg-brand-soft text-brand'"
                                x-text="pct(t) + '%'"
                            ></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ========================================================
            TAMPILAN DESKTOP (>= md): Tabel Grid Penuh
        ========================================================= --}}
        <div class="hidden md:block">

            {{-- Kepala tabel --}}
            <div class="grid grid-cols-[1.4fr_3fr_1.4fr_1fr] gap-6 border-b border-line px-6 py-4 text-[11px] font-semibold uppercase tracking-wide text-muted">
                <span>{{ __('saving.target_name') }}</span>
                <span>{{ __('saving.progress') }}</span>
                <span>{{ __('saving.amount') }}</span>
                <span>{{ __('saving.action') }}</span>
            </div>

            {{-- Kepala tabel --}}
            <div class="grid grid-cols-[1.4fr_3fr_1.4fr_1fr] gap-6 border-b border-line px-6 py-4 text-[11px] font-semibold uppercase tracking-wide text-muted">
                <span>{{ __('saving.target_name') }}</span>
                <span>{{ __('saving.progress') }}</span>
                <span>{{ __('saving.amount') }}</span>
                <span>{{ __('saving.action') }}</span>
            </div>

            {{-- Baris --}}
            <template x-for="t in targets" :key="t.id">

                <div class="grid grid-cols-[1.4fr_3fr_1.4fr_1fr] items-center gap-6 border-b border-line px-6 py-5">

                    {{-- Nama --}}
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
                            <x-icon name="target" size="h-4 w-4" />
                        </span>
                        <div class="leading-tight min-w-0">
                            <p class="truncate text-sm font-semibold" x-text="t.name"></p>
                            <p class="truncate text-xs text-muted" x-text="t.note"></p>
                            <p class="mt-0.5 text-xs text-muted">{{ __('saving.from_wallet') }} <span x-text="t.wallet"></span></p>
                        </div>
                    </div>

                    {{-- Progress --}}
                    <div>
                        <div class="flex items-center justify-between text-xs text-muted">
                            <span x-text="rp(t.saved) + ' ' + @js(__('saving.saved_suffix'))"></span>
                            <span class="rounded-full bg-brand-soft px-2.5 py-1 font-medium text-brand tabular-nums"
                                  x-text="pct(t) + '%'"></span>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-page overflow-hidden">
                            <div class="h-2 rounded-full bg-brand transition-all duration-500" :style="'width: ' + pct(t) + '%'"></div>
                        </div>
                    </div>

                    {{-- Amount --}}
                    <div class="leading-tight tabular-nums">
                        <p class="text-sm font-semibold" x-text="rp(t.saved)"></p>
                        <p class="text-xs text-muted" x-text="@js(__('saving.of')) + ' ' + rp(t.target)"></p>
                    </div>

                    {{-- Action --}}
                    <div>
                        <x-button variant="danger" x-on:click="promptDelete(t)">
                            <x-icon name="trash" size="h-4 w-4" /> {{ __('saving.delete') }}
                        </x-button>
                    </div>

                </div>

            </template>

        </div>

        {{-- Kosong --}}
        <template x-if="targets.length === 0">
            <p class="px-6 py-10 text-center text-sm text-muted">
                {{ __('saving.no_targets') }}
            </p>
        </template>

        {{-- Footer --}}
        <div class="border-t border-line px-6 py-4 text-xs text-muted"
             x-text="targets.length + ' ' + @js(__('saving.active_targets'))"></div>

    </x-card>

    {{-- Custom Confirmation Modal --}}
    <div
        x-cloak
        x-show="confirmDialog.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:keydown.escape.window.stop="if (confirmDialog.open && !isDeleting) { closeConfirm(); $event.stopPropagation(); }"
        x-on:click.self="if (!isDeleting) closeConfirm()"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
    >
        <div
            class="w-full max-w-sm rounded-2xl border border-line bg-white p-6 shadow-2xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-expense-soft text-expense">
                    <x-icon name="trash" size="h-5 w-5" />
                </div>

                <button
                    type="button"
                    x-on:click="if (!isDeleting) closeConfirm()"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-line text-muted hover:bg-page hover:text-ink transition disabled:opacity-50 disabled:cursor-not-allowed"
                    x-bind:disabled="isDeleting"
                >
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <div class="mt-4">
                <h4 class="text-lg font-bold text-ink">Delete Saving Target?</h4>
                <p class="mt-2 text-sm text-muted leading-relaxed">
                    Are you sure you want to delete the <span class="font-medium text-ink" x-text="confirmDialog.targetName"></span> target? This action cannot be undone.
                </p>
                <p x-show="deleteError" x-cloak class="mt-3 text-sm text-expense" x-text="deleteError"></p>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <x-button
                    variant="outline"
                    x-on:click="if (!isDeleting) closeConfirm()"
                    class="w-full justify-center sm:w-auto"
                    x-bind:disabled="isDeleting"
                >
                    Cancel
                </x-button>

                <x-button
                    variant="danger"
                    x-on:click="executeDelete()"
                    class="w-full justify-center sm:w-auto"
                    x-bind:disabled="isDeleting"
                >
                    <span x-show="!isDeleting">Delete</span>
                    <span x-show="isDeleting" class="inline-flex items-center gap-1.5" x-cloak>
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        Deleting...
                    </span>
                </x-button>
            </div>
        </div>
    </div>

</div>

@endsection