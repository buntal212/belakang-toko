<?php

namespace App\Http\Requests\Api\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class SupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'rekening' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get the custom error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama supplier wajib diisi',
            'nama.string' => 'Nama supplier harus berupa teks',
            'nama.max' => 'Nama supplier maksimal 150 karakter',
            'alamat.string' => 'Alamat harus berupa teks',
            'telepon.string' => 'Telepon harus berupa teks',
            'telepon.max' => 'Telepon maksimal 30 karakter',
            'rekening.string' => 'Rekening harus berupa teks',
            'rekening.max' => 'Rekening maksimal 100 karakter',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $firstError = $validator->errors()->first();

        throw new HttpResponseException(
            new JsonResponse([
                'success' => false,
                'message' => $firstError,
            ], 422)
        );
    }
}