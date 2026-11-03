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
            'saldo' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999', 'prohibits:saldo_awal'],
            'saldo_awal' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999', 'prohibits:saldo'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_dompet.required' => 'The wallet name is required.',
            'nama_dompet.max' => 'The wallet name may not be greater than 100 characters.',
            'saldo.numeric' => 'Current balance must be a number.',
            'saldo.min' => 'Current balance cannot be negative.',
            'saldo.prohibits' => 'You can only fill either Current Balance or Initial Balance.',
            'saldo_awal.numeric' => 'Initial balance must be a number.',
            'saldo_awal.min' => 'Initial balance cannot be negative.',
            'saldo_awal.prohibits' => 'You can only fill either Current Balance or Initial Balance.',
        ];
    }
}
