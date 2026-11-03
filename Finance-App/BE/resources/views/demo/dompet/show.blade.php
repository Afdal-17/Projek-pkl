<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">{{ $dompet->nama_dompet }}</h2></x-slot>
    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8"><div class="bg-white p-6 shadow-sm rounded-lg space-y-4"><div><span class="text-sm text-gray-500">Saldo</span><p class="text-2xl font-semibold">Rp {{ number_format((float) $dompet->saldo, 2, ',', '.') }}</p></div><p class="text-gray-600">{{ $dompet->deskripsi ?: 'Tidak ada deskripsi.' }}</p><div class="flex justify-end gap-3"><a href="{{ route('dompet.index') }}" class="px-4 py-2 text-gray-600">Kembali</a><a href="{{ route('dompet.edit', $dompet) }}" class="px-4 py-2 bg-gray-800 text-white rounded-md">Edit</a></div></div></div>
</x-app-layout>
