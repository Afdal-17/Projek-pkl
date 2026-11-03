<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_kategori' => ['required', 'string', 'max:100'],
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kategori.required' => 'Nama kategori wajib diisi.',
            'nama_kategori.max' => 'Nama kategori maksimal 100 karakter.',
            'jenis.required' => 'Jenis kategori wajib dipilih.',
            'jenis.in' => 'Jenis kategori harus pemasukan atau pengeluaran.',
        ];
    }
}
