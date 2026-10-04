<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->getAuthIdentifier();

        return [
            'id_dompet' => ['required', 'integer', Rule::exists('dompet', 'id_dompet')->where('id_user', $userId)],
            'id_kategori' => [
                'required',
                'integer',
                Rule::exists('kategori', 'id_kategori')
                    ->where('id_user', $userId)
                    ->where('jenis', $this->input('jenis')),
            ],
            'nama_transaksi' => ['required', 'string', 'max:150'],
            'jumlah' => ['required', 'numeric', 'gt:0'],
            'jenis' => ['required', Rule::in(['pemasukan', 'pengeluaran'])],
            'tanggal' => ['required', 'date'],
        ];
    }
}
