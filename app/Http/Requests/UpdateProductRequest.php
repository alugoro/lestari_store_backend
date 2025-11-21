<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('id'); // Get ID dari route parameter

        return [
            'product_type_id' => 'sometimes|exists:product_types,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:products,code,' . $productId,
            'description' => 'nullable|string',
            'price_per_unit' => 'sometimes|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'current_stock' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|in:ons,pcs',
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
            'product_type_id.exists' => 'Tipe produk tidak valid',
            'code.unique' => 'Kode produk sudah digunakan',
            'price_per_unit.min' => 'Harga jual minimal 0',
            'unit.in' => 'Satuan harus ons atau pcs',
            'image.image' => 'File harus berupa gambar',
            'image.mimes' => 'Format gambar harus jpeg, png, jpg, atau gif',
            'image.max' => 'Ukuran gambar maksimal 2MB',
        ];
    }
}