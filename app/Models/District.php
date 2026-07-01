<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'governorate_id',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // العلاقات
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }

    // Scope للنشط
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // الحصول على الاسم حسب اللغة
    public function getDisplayNameAttribute()
    {
        return app()->getLocale() === 'ar' && $this->name_ar 
            ? $this->name_ar 
            : $this->name;
    }
}