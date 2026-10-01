<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nama_kategori' => ['required', 'string', 'max:100'],
            'jenis' => ['required', Rule::in(['pemasukan', 'pengeluaran'])],
        ];
    }
}
