<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_code',
        'user_id',
        'supplier_name',
        'total_amount',
        'status',
        'notes',
        'purchase_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'purchase_date' => 'datetime',
    ];

    /**
     * Relasi: Purchase belongs to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi: Purchase has many PurchaseItems
     */
    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
}