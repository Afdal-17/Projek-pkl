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
            'saldo_awal' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_dompet.required' => 'Nama dompet wajib diisi.',
            'nama_dompet.max' => 'Nama dompet maksimal 100 karakter.',
            'saldo_awal.required' => 'Saldo awal wajib diisi.',
            'saldo_awal.numeric' => 'Saldo awal harus berupa angka.',
            'saldo_awal.min' => 'Saldo awal tidak boleh negatif.',
        ];
    }
}
