<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->isBanned() || ! $user->status || $user->email_verified_at === null) {
            return false;
        }

        return true;
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
            'jumlah' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(?:\.\d{1,2})?$/', 'max:999999999999999999.99'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'tanggal_transfer' => ['required', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (
                $this->filled('id_dompet_asal')
                && $this->filled('id_dompet_tujuan')
                && (int) $this->input('id_dompet_asal') === (int) $this->input('id_dompet_tujuan')
            ) {
                $validator->errors()->add('id_dompet_tujuan', 'Destination wallet must be different from source wallet.');
            }

            if ($this->filled('id_dompet_tujuan')) {
                $targetWallet = \App\Models\Dompet::with('user')->find($this->input('id_dompet_tujuan'));
                if ($targetWallet && $targetWallet->user && (int) $targetWallet->id_user !== (int) $this->user()->getAuthIdentifier()) {
                    if ($targetWallet->user->isBanned()) {
                        $validator->errors()->add('id_dompet_tujuan', 'Recipient account is suspended and cannot receive transfers.');
                    } elseif (
                        ! $targetWallet->user->status
                        || $targetWallet->user->role !== 'user'
                        || $targetWallet->user->email_verified_at === null
                    ) {
                        $validator->errors()->add('id_dompet_tujuan', 'Recipient account is inactive or unverified.');
                    } elseif (! $targetWallet->terima_transfer) {
                        $validator->errors()->add('id_dompet_tujuan', 'Recipient wallet is not enabled to receive transfers.');
                    }
                }
            }
        });
    }
}
