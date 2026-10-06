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
        $this->merge([
            'nama' => $this->input('nama', $this->input('name')),
            'nomor_telepon' => $this->input('nomor_telepon', $this->input('phone', $this->user()->nomor_telepon)),
            'lokasi' => $this->input('lokasi', $this->input('location', $this->user()->lokasi)),
            'mata_uang' => $this->input('mata_uang', $this->input('currency', $this->user()->mata_uang ?? 'IDR')),
            'awal_minggu' => $this->input('awal_minggu', $this->input('start_of_week', $this->user()->awal_minggu ?? 'monday')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nomor_telepon' => ['nullable', 'string', 'max:32'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'mata_uang' => ['required', 'string', 'in:IDR,USD,EUR'],
            'awal_minggu' => ['required', 'string', 'in:monday,sunday'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->getAuthIdentifier(), 'id_user'),
            ],
        ];
    }
}
