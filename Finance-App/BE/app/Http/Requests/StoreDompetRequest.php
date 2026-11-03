<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDompetRequest extends FormRequest
{
    private const MAX_SALDO = '999999999999999999.99';

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

        // Warna bersifat kosmetik dan boleh digunakan ulang antar dompet.
        // Bila tidak dikirim, controller memilih warna awal berdasarkan dompet yang ada.
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
                    $fail(__('dompet.balance_limit'));
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
                'max:999999999999999999.99',
                'prohibits:saldo_awal',
            ],

            'saldo_awal' => [
                ...$saldoRules,
                'numeric',
                'min:0',
                'max:999999999999999999.99',
                'prohibits:saldo',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'saldo.regex' => 'Current balance must be a number with up to two decimal places.',
            'saldo.numeric' => 'Current balance must be a number.',
            'saldo.min' => 'Current balance cannot be negative.',
            'saldo.prohibits' => 'You can only fill either Current Balance or Initial Balance.',
            'saldo_awal.regex' => 'Initial balance must be a number with up to two decimal places.',
            'saldo_awal.numeric' => 'Initial balance must be a number.',
            'saldo_awal.min' => 'Initial balance cannot be negative.',
            'saldo_awal.prohibits' => 'You can only fill either Current Balance or Initial Balance.',
        ];
    }
}
