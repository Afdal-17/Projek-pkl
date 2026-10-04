<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">{{ $kategori->nama_kategori }}</h2></x-slot>
    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8"><div class="bg-white p-6 shadow-sm rounded-lg space-y-4"><p><span class="text-sm text-gray-500">Jenis</span><br><span class="capitalize">{{ $kategori->jenis }}</span></p><div class="flex justify-end gap-3"><a href="{{ route('kategori.index') }}" class="px-4 py-2 text-gray-600">Kembali</a><a href="{{ route('kategori.edit', $kategori) }}" class="px-4 py-2 bg-gray-800 text-white rounded-md">Edit</a></div></div></div>
</x-app-layout>
