<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_code',
        'user_id',
        'total_amount',
        'total_profit',
        'paid_amount',
        'change_amount',
        'payment_method',
        'notes',
        'transaction_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    /**
     * Relasi: Transaction belongs to User (kasir)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi: Transaction has many TransactionItems
     */
    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
}