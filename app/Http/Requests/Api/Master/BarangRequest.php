<?php

namespace App\Http\Requests\Api\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class BarangRequest extends FormRequest
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

            'namabarang' => ['required', 'string', 'max:255'],
            'jenisbarang' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:100'],
            'satuanbesar' => ['nullable', 'string', 'max:50'],
            'satuankecil' => ['nullable', 'string', 'max:50'],
            'isisatuan' => ['required', 'numeric', 'min:1'],
            'limitstok' => ['nullable', 'numeric'],
            'hargajual_satuankecil' => ['nullable', 'numeric'],
            'hargajual_satuanbesar' => ['nullable', 'numeric'],
        ];
    }

    /**
     * Get the custom error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'namabarang.required' => 'Nama barang wajib diisi',
            'namabarang.string' => 'Nama barang harus berupa teks',
            'namabarang.max' => 'Nama barang maksimal 255 karakter',
            'jenisbarang.string' => 'Jenis barang harus berupa teks',
            'jenisbarang.max' => 'Jenis barang maksimal 100 karakter',
            'keterangan.string' => 'Keterangan harus berupa teks',
            'keterangan.max' => 'Keterangan maksimal 255 karakter',
            'merk.string' => 'Merk harus berupa teks',
            'merk.max' => 'Merk maksimal 100 karakter',
            'satuanbesar.string' => 'Satuan besar harus berupa teks',
            'satuanbesar.max' => 'Satuan besar maksimal 50 karakter',
            'satuankecil.string' => 'Satuan kecil harus berupa teks',
            'satuankecil.max' => 'Satuan kecil maksimal 50 karakter',
            'isisatuan.required' => 'Isi satuan wajib diisi',
            'isisatuan.numeric' => 'Isi satuan harus berupa angka',
            'isisatuan.min' => 'Isi satuan minimal 1',
            'limitstok.numeric' => 'Limit stok harus berupa angka',
            'hargajual_satuankecil.numeric' => 'Harga jual satuan kecil harus berupa angka',
            'hargajual_satuanbesar.numeric' => 'Harga jual satuan besar harus berupa angka',
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
