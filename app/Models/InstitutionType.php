<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstitutionType extends Model
{
    use HasFactory;

    protected $table = 'institution_types';

    protected $fillable = [
        'name',
        'name_ar',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== Relationships ====================

    /**
     * المؤسسات التي تنتمي لهذا النوع
     */
    public function institutions()
    {
        return $this->hasMany(Institution::class, 'type_id');
    }

    // ==================== Scopes ====================

    /**
     * فلتر الأنواع النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ==================== Accessors ====================

    /**
     * عدد المؤسسات من هذا النوع
     */
    public function getInstitutionsCountAttribute()
    {
        return $this->institutions()->count();
    }

    /**
     * عدد المؤسسات النشطة من هذا النوع
     */
    public function getActiveInstitutionsCountAttribute()
    {
        return $this->institutions()->where('status', 'active')->count();
    }
}