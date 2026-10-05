<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if (isset($summary))
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="bg-white p-6 shadow-sm rounded-lg"><p class="text-sm text-gray-500">Total Saldo</p><p class="mt-2 text-2xl font-semibold">Rp {{ number_format($summary['total_saldo'], 2, ',', '.') }}</p></div>
                <div class="bg-white p-6 shadow-sm rounded-lg"><p class="text-sm text-gray-500">Pemasukan Bulan Ini</p><p class="mt-2 text-2xl font-semibold text-green-600">Rp {{ number_format($summary['total_pemasukan_bulan_ini'], 2, ',', '.') }}</p></div>
                <div class="bg-white p-6 shadow-sm rounded-lg"><p class="text-sm text-gray-500">Pengeluaran Bulan Ini</p><p class="mt-2 text-2xl font-semibold text-red-600">Rp {{ number_format($summary['total_pengeluaran_bulan_ini'], 2, ',', '.') }}</p></div>
            </div>
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section class="bg-white p-6 shadow-sm rounded-lg"><h3 class="font-semibold text-gray-900">Pengeluaran per Kategori</h3><div class="mt-4 space-y-3">@forelse ($summary['pengeluaran_per_kategori'] as $category)<div class="flex justify-between"><span>{{ $category['nama_kategori'] }}</span><span>Rp {{ number_format($category['total'], 2, ',', '.') }}</span></div>@empty<p class="text-gray-500">Belum ada pengeluaran bulan ini.</p>@endforelse</div></section>
                <section class="bg-white p-6 shadow-sm rounded-lg"><h3 class="font-semibold text-gray-900">Target Tabungan</h3><div class="mt-4 space-y-4">@forelse ($summary['target_tabungan'] as $target)<div><div class="flex justify-between text-sm"><span>{{ $target['nama_target'] }}</span><span>{{ number_format($target['progress'], 2, ',', '.') }}%</span></div><div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200"><div class="h-full bg-green-600" style="width: {{ $target['progress'] }}%"></div></div></div>@empty<p class="text-gray-500">Belum ada target tabungan.</p>@endforelse</div></section>
            </div>
        @else
            <div class="bg-white p-6 shadow-sm rounded-lg"><div class="text-gray-900">{{ $title ?? __('Dashboard') }}</div></div>
        @endif
    </div>
</x-app-layout>
