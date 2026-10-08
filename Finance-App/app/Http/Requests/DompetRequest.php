<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DompetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_dompet' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:65535'],
            'saldo' => ['nullable', 'numeric', 'min:0', 'prohibits:saldo_awal'],
            'saldo_awal' => ['nullable', 'numeric', 'min:0', 'prohibits:saldo'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_dompet.required' => 'Nama dompet wajib diisi.',
            'nama_dompet.max' => 'Nama dompet maksimal 100 karakter.',
            'saldo.numeric' => 'Current balance harus berupa angka.',
            'saldo.min' => 'Current balance tidak boleh negatif.',
            'saldo.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
            'saldo_awal.numeric' => 'Saldo awal harus berupa angka.',
            'saldo_awal.min' => 'Saldo awal tidak boleh negatif.',
            'saldo_awal.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
        ];
    }
}
