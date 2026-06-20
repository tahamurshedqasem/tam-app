<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class InstitutionOwner extends Pivot
{
    protected $table = 'institution_owners';

    protected $fillable = [
        'user_id',
        'institution_id',
        'is_primary'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== Relationships ====================

    /**
     * المستخدم (المالك)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * المؤسسة
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}