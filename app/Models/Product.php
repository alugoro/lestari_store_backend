<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_type_id',
        'name',
        'code',
        'description',
        'image_url',
        'price_per_unit',
        'purchase_price',
        'current_stock',
        'unit',
        'is_active',
    ];

    protected $casts = [
        'price_per_unit' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'current_stock' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi: Product belongs to ProductType
     */
    public function productType()
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Relasi: Product has many TransactionItems
     */
    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Relasi: Product has many StockMovements
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}