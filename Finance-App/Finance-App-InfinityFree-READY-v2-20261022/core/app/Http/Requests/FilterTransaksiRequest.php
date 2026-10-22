<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->getAuthIdentifier();

        return [
            'q' => ['nullable', 'string', 'max:150'],
            'jenis' => ['nullable', Rule::in(['pemasukan', 'pengeluaran'])],
            'id_dompet' => ['nullable', 'integer', Rule::exists('dompet', 'id_dompet')->where('id_user', $userId)],
            'id_kategori' => ['nullable', 'integer', Rule::exists('kategori', 'id_kategori')->where('id_user', $userId)],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ];
    }
}
