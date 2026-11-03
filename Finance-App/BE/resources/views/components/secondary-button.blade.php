<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink shadow-sm transition hover:bg-page focus:outline-none focus:ring-2 focus:ring-brand/20 active:scale-[0.99] disabled:opacity-50']) }}>
    {{ $slot }}
</button>
