<?php

namespace App\Http\Requests\Api\Gudang;

use Illuminate\Foundation\Http\FormRequest;

class PenerimaanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:tpenerimaan,id'],
            'nomorfaktur' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['required', 'date'],
            'tglfaktur' => ['nullable', 'date'],
            'supplier_id' => ['required', 'integer', 'exists:msupplier,id'],
            'cara_bayar' => ['required', 'in:CASH,HUTANG'],
            'catatan' => ['nullable', 'string'],
            'status' => ['nullable', 'in:Draft,Terkunci'],
            'pajakpersen' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.barang_id' => ['required', 'integer', 'distinct', 'exists:mbarang,id'],
            'rincian.*.qtybesar' => ['required', 'numeric', 'gt:0'],
            'rincian.*.isi' => ['required', 'integer', 'min:1'],
            'rincian.*.hargabeli' => ['required', 'numeric', 'min:0'],
            'rincian.*.diskonpersen' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rincian.*.diskonnominal' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier wajib dipilih',
            'cara_bayar.required' => 'Cara bayar wajib dipilih',
            'cara_bayar.in' => 'Cara bayar harus CASH atau HUTANG',
            'rincian.required' => 'Rincian barang wajib diisi',
            'rincian.min' => 'Minimal satu barang harus ditambahkan',
            'rincian.*.barang_id.distinct' => 'Barang yang sama tidak boleh ditambahkan dua kali',
        ];
    }
}
