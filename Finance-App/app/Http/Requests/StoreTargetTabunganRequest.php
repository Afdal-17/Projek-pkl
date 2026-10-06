<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTargetTabunganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'id_dompet' => [
                'required',
                'integer',
                Rule::exists('dompet', 'id_dompet')->where('id_user', $this->user()->getAuthIdentifier()),
            ],
            'nama_target' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'nominal_target' => ['required', 'numeric', 'gt:0'],
            'initial_savings' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
