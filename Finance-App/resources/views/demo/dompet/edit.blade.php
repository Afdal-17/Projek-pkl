<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Edit Dompet</h2></x-slot>
    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8"><div class="bg-white p-6 shadow-sm rounded-lg"><form method="POST" action="{{ route('dompet.update', $dompet) }}" class="space-y-5">@csrf @method('PUT') @include('dompet.form')<div class="flex justify-end gap-3"><a href="{{ route('dompet.index') }}" class="px-4 py-2 text-gray-600">Batal</a><button class="px-4 py-2 bg-gray-800 text-white rounded-md">Simpan Perubahan</button></div></form></div></div>
</x-app-layout>
