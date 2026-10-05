<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Tambah Dompet</h2></x-slot>
    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8"><div class="bg-white p-6 shadow-sm rounded-lg"><form method="POST" action="{{ route('dompet.store') }}" class="space-y-5">@csrf @include('dompet.form', ['dompet' => null])<div class="flex justify-end gap-3"><a href="{{ route('dompet.index') }}" class="px-4 py-2 text-gray-600">Batal</a><button class="px-4 py-2 bg-gray-800 text-white rounded-md">Simpan</button></div></form></div></div>
</x-app-layout>
