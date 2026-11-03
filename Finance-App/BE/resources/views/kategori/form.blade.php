<div>
    <label for="nama_kategori" class="block text-sm font-medium text-gray-700">{{ __('transactions.name') }}</label>
    <input id="nama_kategori" name="nama_kategori" value="{{ old('nama_kategori', $kategori?->nama_kategori) }}" required class="mt-1 block w-full rounded-md border-gray-300">
    <x-input-error :messages="$errors->get('nama_kategori')" class="mt-2" />
</div>
<div>
    <label for="jenis" class="block text-sm font-medium text-gray-700">{{ __('transactions.type') }}</label>
    <select id="jenis" name="jenis" required class="mt-1 block w-full rounded-md border-gray-300">
        <option value="">{{ __('transactions.type') }}</option>
        <option value="pemasukan" @selected(old('jenis', $kategori?->jenis) === 'pemasukan')>{{ __('transactions.income') }}</option>
        <option value="pengeluaran" @selected(old('jenis', $kategori?->jenis) === 'pengeluaran')>{{ __('transactions.expense') }}</option>
    </select>
    <x-input-error :messages="$errors->get('jenis')" class="mt-2" />
</div>
