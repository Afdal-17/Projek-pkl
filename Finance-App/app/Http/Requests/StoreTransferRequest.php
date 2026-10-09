<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->getAuthIdentifier();

        return [
            'id_dompet_asal' => [
                'required',
                'integer',
                Rule::exists('dompet', 'id_dompet')->where('id_user', $userId),
            ],
            'id_dompet_tujuan' => ['required', 'integer', Rule::exists('dompet', 'id_dompet')],
            'jumlah' => ['required', 'numeric', 'gt:0'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'tanggal_transfer' => ['required', 'date'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('id_dompet_asal') && $this->input('id_dompet_asal') === $this->input('id_dompet_tujuan')) {
                $validator->errors()->add('id_dompet_tujuan', 'Dompet tujuan harus berbeda dari dompet asal.');
            }

            if ($this->filled('id_dompet_tujuan')) {
                $targetWallet = \App\Models\Dompet::with('user')->find($this->input('id_dompet_tujuan'));
                if ($targetWallet && $targetWallet->user && $targetWallet->user->isBanned()) {
                    $validator->errors()->add('id_dompet_tujuan', 'Akun penerima sedang ditangguhkan (banned) oleh admin dan tidak dapat menerima transfer.');
                }
            }
        });
    }
}
