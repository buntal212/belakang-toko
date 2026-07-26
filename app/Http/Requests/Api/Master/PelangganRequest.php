<?php

namespace App\Http\Requests\Api\Master;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class PelangganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:mpelanggan,id'],
            'nama' => ['required', 'string', 'max:150'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.integer' => 'ID pelanggan tidak valid',
            'id.exists' => 'Data pelanggan tidak ditemukan',
            'nama.required' => 'Nama pelanggan wajib diisi',
            'nama.string' => 'Nama pelanggan harus berupa teks',
            'nama.max' => 'Nama pelanggan maksimal 150 karakter',
            'alamat.string' => 'Alamat harus berupa teks',
            'telepon.string' => 'Telepon harus berupa teks',
            'telepon.max' => 'Telepon maksimal 30 karakter',
            'email.email' => 'Format email tidak valid',
            'email.max' => 'Email maksimal 150 karakter',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            new JsonResponse([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
