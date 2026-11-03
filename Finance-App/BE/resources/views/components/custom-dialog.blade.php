<div
    x-data="{
        dialog: {
            open: false,
            title: '',
            message: '',
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            variant: 'brand',
            isAlert: false,
            loading: false,
            resolve: null,
            onConfirm: null,
        },
        toast: {
            show: false,
            message: '',
            variant: 'income',
            timeout: null,
        },

        init() {
            window.financeConfirm = (options = {}) => {
                const opts = typeof options === 'string' ? { message: options } : options;
                return new Promise((resolve) => {
                    this.dialog = {
                        open: true,
                        title: opts.title || 'Confirm Action',
                        message: opts.message || 'Are you sure you want to proceed?',
                        confirmText: opts.confirmText || 'Confirm',
                        cancelText: opts.cancelText || 'Cancel',
                        variant: opts.variant || 'brand',
                        icon: opts.icon || (opts.variant === 'danger' ? 'trash' : 'alert-circle'),
                        isAlert: false,
                        loading: false,
                        resolve: resolve,
                        onConfirm: opts.onConfirm || null,
                    };
                });
            };

            window.financeAlert = (options = {}) => {
                const opts = typeof options === 'string' ? { message: options } : options;
                return new Promise((resolve) => {
                    this.dialog = {
                        open: true,
                        title: opts.title || 'Notification',
                        message: opts.message || '',
                        confirmText: opts.confirmText || 'OK',
                        cancelText: '',
                        variant: opts.variant || 'brand',
                        icon: opts.icon || (opts.variant === 'income' ? 'check' : (opts.variant === 'warn' ? 'ban' : 'alert-circle')),
                        isAlert: true,
                        loading: false,
                        resolve: resolve,
                        onConfirm: null,
                    };
                });
            };

            window.financeToast = (options = {}) => {
                const opts = typeof options === 'string' ? { message: options } : options;
                if (this.toast.timeout) clearTimeout(this.toast.timeout);
                this.toast.message = opts.message || '';
                this.toast.variant = opts.variant || 'income';
                this.toast.show = true;
                this.toast.timeout = setTimeout(() => {
                    this.toast.show = false;
                }, opts.duration || 3500);
            };
        },

        handleConfirm() {
            if (this.dialog.loading) return;
            const onConfirm = this.dialog.onConfirm;
            const resolve = this.dialog.resolve;
            this.dialog.open = false;
            if (resolve) resolve(true);
            if (typeof onConfirm === 'function') onConfirm();
        },

        handleCancel() {
            if (this.dialog.loading) return;
            const resolve = this.dialog.resolve;
            this.dialog.open = false;
            if (resolve) resolve(false);
        }
    }"
    x-on:keydown.escape.window.stop="if (dialog.open && !dialog.loading) { handleCancel(); $event.stopPropagation(); }"
>
    {{-- Custom Confirmation / Alert Modal --}}
    <div
        x-cloak
        x-show="dialog.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click.self="if (!dialog.loading) handleCancel()"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
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
                {{-- Dynamic Icon Badge --}}
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                    :class="{
                        'bg-expense-soft text-expense': dialog.variant === 'danger',
                        'bg-income-soft text-income': dialog.variant === 'income',
                        'bg-warn-soft text-warn': dialog.variant === 'warn',
                        'bg-brand-soft text-brand': dialog.variant === 'brand' || !dialog.variant
                    }"
                >
                    <template x-if="dialog.icon === 'trash'">
                        <x-icon name="trash" size="h-5 w-5" />
                    </template>
                    <template x-if="dialog.icon === 'transfer'">
                        <x-icon name="transfer" size="h-5 w-5" />
                    </template>
                    <template x-if="dialog.icon === 'check'">
                        <x-icon name="check" size="h-5 w-5" />
                    </template>
                    <template x-if="dialog.icon === 'ban'">
                        <x-icon name="ban" size="h-5 w-5" />
                    </template>
                    <template x-if="!['trash', 'transfer', 'check', 'ban'].includes(dialog.icon)">
                        <x-icon name="alert-circle" size="h-5 w-5" />
                    </template>
                </div>

                <button
                    type="button"
                    x-on:click="if (!dialog.loading) handleCancel()"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-line text-muted hover:bg-page hover:text-ink transition disabled:opacity-50"
                    x-bind:disabled="dialog.loading"
                    aria-label="Close dialog"
                >
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <div class="mt-4">
                <h4 class="text-lg font-bold text-ink" x-text="dialog.title"></h4>
                <p class="mt-2 text-sm text-muted leading-relaxed break-words" x-text="dialog.message"></p>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <template x-if="!dialog.isAlert">
                    <x-button
                        variant="outline"
                        type="button"
                        x-on:click="handleCancel()"
                        class="w-full justify-center sm:w-auto"
                        x-bind:disabled="dialog.loading"
                    >
                        <span x-text="dialog.cancelText"></span>
                    </x-button>
                </template>

                {{-- Action / Confirm Button Variants --}}
                <template x-if="dialog.variant === 'danger'">
                    <x-button
                        variant="danger"
                        type="button"
                        x-on:click="handleConfirm()"
                        class="w-full justify-center sm:w-auto"
                        x-bind:disabled="dialog.loading"
                    >
                        <span x-text="dialog.confirmText"></span>
                    </x-button>
                </template>

                <template x-if="dialog.variant === 'income'">
                    <button
                        type="button"
                        x-on:click="handleConfirm()"
                        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition bg-income text-white hover:opacity-90 w-full sm:w-auto"
                        x-bind:disabled="dialog.loading"
                    >
                        <span x-text="dialog.confirmText"></span>
                    </button>
                </template>

                <template x-if="dialog.variant !== 'danger' && dialog.variant !== 'income'">
                    <x-button
                        variant="brand"
                        type="button"
                        x-on:click="handleConfirm()"
                        class="w-full justify-center sm:w-auto"
                        x-bind:disabled="dialog.loading"
                    >
                        <span x-text="dialog.confirmText"></span>
                    </x-button>
                </template>
            </div>
        </div>
    </div>

    {{-- Custom Floating Toast Notification --}}
    <div
        x-cloak
        x-show="toast.show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:translate-x-3"
        x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed bottom-5 right-5 z-[110] max-w-sm flex items-center gap-3 rounded-xl border border-line bg-white px-4 py-3 shadow-xl backdrop-blur-sm"
        role="alert"
    >
        <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
            :class="{
                'bg-income-soft text-income': toast.variant === 'income',
                'bg-expense-soft text-expense': toast.variant === 'expense' || toast.variant === 'danger',
                'bg-warn-soft text-warn': toast.variant === 'warn',
                'bg-brand-soft text-brand': toast.variant === 'brand' || !toast.variant
            }"
        >
            <template x-if="toast.variant === 'income'">
                <x-icon name="check" size="h-4 w-4" />
            </template>
            <template x-if="toast.variant === 'expense' || toast.variant === 'danger'">
                <x-icon name="ban" size="h-4 w-4" />
            </template>
            <template x-if="toast.variant === 'warn'">
                <x-icon name="alert-circle" size="h-4 w-4" />
            </template>
            <template x-if="toast.variant === 'brand' || !toast.variant">
                <x-icon name="alert-circle" size="h-4 w-4" />
            </template>
        </div>

        <p class="text-sm font-medium text-ink flex-1" x-text="toast.message"></p>

        <button
            type="button"
            x-on:click="toast.show = false"
            class="text-muted hover:text-ink transition shrink-0 p-1"
            aria-label="Dismiss notification"
        >
            <x-icon name="x" size="h-3.5 w-3.5" />
        </button>
    </div>
</div>
