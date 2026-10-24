<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl bg-expense px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-expense/20 active:scale-[0.99] disabled:opacity-50']) }}>
    {{ $slot }}
</button>
