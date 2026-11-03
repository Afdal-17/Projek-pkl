<?php

namespace App\Http\Requests;

class UpdateDompetRequest extends StoreDompetRequest
{
	public function rules(): array
	{
		return [
			'nama_dompet' => ['required', 'string', 'max:100'],
			'deskripsi' => ['nullable', 'string'],
		];
	}
}
