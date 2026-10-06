<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDompetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'jenis' => $this->input('jenis', 'digital'),
            'warna' => strtolower((string) $this->input('warna', 'brand')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nama_dompet' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'jenis' => ['required', 'string', 'in:physical,digital,bank'],
            'warna' => [
                'required',
                'string',
                'regex:/^(brand|income|warn|expense|dark|#[0-9a-fA-F]{6})$/',
                Rule::unique('dompet', 'warna')
                    ->where('id_user', $this->user()->id_user)
                    ->ignore($this->route('dompet')?->id_dompet, 'id_dompet'),
            ],
            'saldo_awal' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'warna.unique' => 'Wallet tidak bisa ditambah karena warna penentu sama.',
        ];
    }
}
