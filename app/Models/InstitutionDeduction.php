<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstitutionDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'membership_number',
        'amount',
        'currency',
        'deduction_type',
        'status',
        'description',
        'deducted_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'deducted_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}