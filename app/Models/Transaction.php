<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\TransactionType;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        "user_id",
        "transaction_type",
        "amount",
        "fee",
        "date",
    ];

    protected $casts = [
        "transaction_type" => TransactionType::class,
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
