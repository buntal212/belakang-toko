<?php

namespace App\Http\Requests\Master\Satuan;

use Illuminate\Foundation\Http\FormRequest;

class StoreSatuanRequest extends FormRequest
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
            'satuan' => ['required', 'string', 'max:100', 'unique:msatuan,satuan'],
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
}