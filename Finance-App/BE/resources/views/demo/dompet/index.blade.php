<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Dompet</h2></x-slot>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-end mb-4"><a href="{{ route('dompet.create') }}" class="px-4 py-2 bg-gray-800 text-white rounded-md">Tambah Dompet</a></div>
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saldo</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($dompet as $item)
                        <tr><td class="px-6 py-4">{{ $item->nama_dompet }}<div class="text-sm text-gray-500">{{ $item->deskripsi }}</div></td><td class="px-6 py-4">Rp {{ number_format((float) $item->saldo, 2, ',', '.') }}</td><td class="px-6 py-4 text-right space-x-2"><a class="text-indigo-600" href="{{ route('dompet.show', $item) }}">Detail</a><a class="text-indigo-600" href="{{ route('dompet.edit', $item) }}">Edit</a><form class="inline" method="POST" action="{{ route('dompet.destroy', $item) }}">@csrf @method('DELETE')<button class="text-red-600" onclick="return confirm('Hapus dompet ini?')">Hapus</button></form></td></tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">Belum ada dompet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $dompet->links() }}</div>
    </div>
</x-app-layout>
