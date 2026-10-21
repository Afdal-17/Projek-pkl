<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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

        // Warna hanya bersifat kosmetik dan boleh sama antar dompet;
        // bila tidak dikirim, controller yang memilih warna yang belum dipakai.
        $warna = $this->input('warna');
        if ($warna === '' || $warna === null) {
            $warna = null;
        } else {
            $warna = strtolower((string) $warna);
        }

        $this->merge([
            'jenis' => $this->input('jenis', 'digital'),
            'warna' => $warna,
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
                'nullable',
                'string',
                'regex:/^(brand|income|warn|expense|dark|#[0-9a-fA-F]{6})$/',
            ],
                    'saldo' => [
                ...$saldoRules,
                'numeric',
                'min:0',
                'max:999999999999999999',
                'prohibits:saldo_awal',
            ],

            'saldo_awal' => [
                ...$saldoRules,
                'numeric',
                'min:0',
                'max:999999999999999999',
                'prohibits:saldo',
            ],
        ];
    }

    public function messages(): array
    {
        return [
        'warna.unique' => 'Wallet tidak bisa ditambah karena warna penentu sama.',
                'saldo.regex' => 'Current balance harus berupa angka dengan maksimal dua angka desimal.',
                'saldo.numeric' => 'Current balance harus berupa angka.',
                'saldo.min' => 'Current balance tidak boleh negatif.',
                'saldo.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
                'saldo_awal.regex' => 'Saldo awal harus berupa angka dengan maksimal dua angka desimal.',
                'saldo_awal.numeric' => 'Saldo awal harus berupa angka.',
                'saldo_awal.min' => 'Saldo awal tidak boleh negatif.',
                'saldo_awal.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
                'saldo.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
                'saldo_awal.regex' => 'Saldo awal harus berupa angka dengan maksimal dua angka desimal.',
                'saldo_awal.prohibits' => 'Hanya boleh mengisi salah satu antara Current Balance atau Saldo Awal.',
        ];
    }
}
