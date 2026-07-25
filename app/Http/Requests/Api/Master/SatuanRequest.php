<?php

namespace App\Http\Requests\Api\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SatuanRequest extends FormRequest
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
        $satuanId = $this->input('id');

        return [
            'satuan' => ['required', 'string', 'max:100', 'unique:msatuan,satuan,' . $satuanId],
        ];
    }

    /**
     * Get the custom error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'satuan.required' => 'Satuan wajib diisi',
            'satuan.string' => 'Satuan harus berupa teks',
            'satuan.max' => 'Satuan maksimal 100 karakter',
            'satuan.unique' => 'Satuan sudah terdaftar',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }
}
