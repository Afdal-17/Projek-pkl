<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $phone = trim((string) ($this->input('nomor_telepon', $this->input('phone', $this->user()->nomor_telepon))));
        if ($phone === '' || $phone === 'null' || strtolower($phone) === 'null') {
            $phone = null;
        }

        $location = trim((string) ($this->input('lokasi', $this->input('location', $this->user()->lokasi))));
        if ($location === '' || $location === 'null' || strtolower($location) === 'null') {
            $location = null;
        }

        $this->merge([
            'nama' => $this->input('nama', $this->input('name')),
            'nomor_telepon' => $phone,
            'lokasi' => $location,
            'mata_uang' => $this->input('mata_uang', $this->input('currency', $this->user()->mata_uang ?? 'IDR')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nomor_telepon' => ['nullable', 'string', 'max:32'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'mata_uang' => ['required', 'string', 'in:IDR,USD,EUR'],
            // Email nullable: user yang mendaftar via OTP WhatsApp tidak memiliki email.
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->getAuthIdentifier(), 'id_user'),
            ],
        ];
    }
}
