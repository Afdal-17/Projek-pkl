<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDompetRequest extends FormRequest
{
    private const MAX_SALDO = '9999999999999999999999999999.99';

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
        $saldoRules = [
            'nullable',
            'regex:/^\d+(?:\.\d{1,2})?$/',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $amount = (string) $value;

                if (bccomp($amount, self::MAX_SALDO, 2) > 0) {
                    $fail('Saldo melebihi batas maksimum yang didukung.');
                }
            },
        ];

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

            'saldo' => [
                ...$saldoRules,
                'prohibits:saldo_awal',
            ],

            'saldo_awal' => [
                ...$saldoRules,
                'prohibits:saldo',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'warna.unique' => 'Wallet tidak bisa ditambah karena warna penentu sama.',
            'saldo.regex' => 'Current balance harus berupa angka dengan maksimal dua angka desimal.',
            'saldo.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
            'saldo_awal.regex' => 'Saldo awal harus berupa angka dengan maksimal dua angka desimal.',
            'saldo_awal.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
        ];
    }
}
