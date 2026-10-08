<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDompetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $saldoAwal = $this->input('saldo_awal');
        if ($saldoAwal === '' || $saldoAwal === null) {
            $saldoAwal = null;
        }

        $saldo = $this->input('saldo');
        if ($saldo === '' || $saldo === null) {
            $saldo = null;
        }

        $this->merge([
            'jenis' => $this->input('jenis', 'digital'),
            'warna' => strtolower((string) $this->input('warna', 'brand')),
            'saldo_awal' => $saldoAwal,
            'saldo' => $saldo,
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
            'saldo' => ['nullable', 'numeric', 'min:0', 'prohibits:saldo_awal'],
            'saldo_awal' => ['nullable', 'numeric', 'min:0', 'prohibits:saldo'],
        ];
    }

    public function messages(): array
    {
        return [
            'warna.unique' => 'Wallet tidak bisa ditambah karena warna penentu sama.',
            'saldo.numeric' => 'Current balance harus berupa angka.',
            'saldo.min' => 'Current balance tidak boleh negatif.',
            'saldo.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
            'saldo_awal.numeric' => 'Saldo awal harus berupa angka.',
            'saldo_awal.min' => 'Saldo awal tidak boleh negatif.',
            'saldo_awal.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
        ];
    }
}
