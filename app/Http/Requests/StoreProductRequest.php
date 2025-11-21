<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Sudah dicek di middleware auth
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_type_id' => 'required|exists:product_types,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:products,code',
            'description' => 'nullable|string',
            'price_per_unit' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'unit' => 'required|string|in:ons,pcs',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'product_type_id.required' => 'Tipe produk harus dipilih',
            'product_type_id.exists' => 'Tipe produk tidak valid',
            'name.required' => 'Nama produk harus diisi',
            'code.required' => 'Kode produk harus diisi',
            'code.unique' => 'Kode produk sudah digunakan',
            'price_per_unit.required' => 'Harga jual harus diisi',
            'price_per_unit.min' => 'Harga jual minimal 0',
            'unit.required' => 'Satuan harus dipilih',
            'unit.in' => 'Satuan harus ons atau pcs',
            'image.image' => 'File harus berupa gambar',
            'image.mimes' => 'Format gambar harus jpeg, png, jpg, atau gif',
            'image.max' => 'Ukuran gambar maksimal 2MB',
        ];
    }
}