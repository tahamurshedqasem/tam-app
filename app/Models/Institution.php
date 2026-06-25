<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Institution extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'institutions';

    protected $fillable = [
        'name',
        'type_id',
        'phone',
        'email',
        'address',
        'discount_percentage',
        'contract_file',
        'agreement_date',
        'agreement_expiry_date',
        'status',
        'owner_id',
        'created_by_marketer',
        'description',
        'business_hours',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'business_hours' => 'array',
        'agreement_date' => 'datetime',
        'agreement_expiry_date' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // ==================== Relationships ====================

    /**
     * نوع المؤسسة
     */
    public function type()
    {
        return $this->belongsTo(InstitutionType::class, 'type_id');
    }

    /**
     * المالك الأساسي للمؤسسة
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * المسوق الذي قام بتسجيل هذه المؤسسة
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_marketer');
    }

    /**
     * جميع مالكي المؤسسة
     */
    public function owners()
    {
        return $this->belongsToMany(User::class, 'institution_owners')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    /**
     * المالك الأساسي من جدول institution_owners
     */
    public function primaryOwner()
    {
        return $this->owners()
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * معاملات الخصم التي تمت في هذه المؤسسة
     */
    public function discountTransactions()
    {
        return $this->hasMany(DiscountTransaction::class, 'institution_id');
    }

    /**
     * العمولات المتعلقة بهذه المؤسسة
     */
    public function commissions()
    {
        return $this->hasManyThrough(Commission::class, DiscountTransaction::class);
    }

    // ==================== Scopes ====================

    /**
     * فلتر المؤسسات النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * فلتر المؤسسات حسب النوع
     */
    public function scopeOfType($query, $typeId)
    {
        return $query->where('type_id', $typeId);
    }

    /**
     * فلتر المؤسسات القريبة جغرافياً
     */
    public function scopeNearby($query, $latitude, $longitude, $distance = 10)
    {
        return $query->whereRaw(
            "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?",
            [$latitude, $longitude, $latitude, $distance]
        );
    }

    /**
     * فلتر المؤسسات التي تنتهي اتفاقيتها قريباً
     */
    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('agreement_expiry_date', '<=', now()->addDays($days))
                     ->where('agreement_expiry_date', '>', now());
    }

    // ==================== Accessors ====================

    /**
     * الحصول على نسبة الخصم كـ float
     */
    public function getDiscountPercentageAttribute($value)
    {
        return (float) ($value ?? 0);
    }

    /**
     * رابط ملف العقد
     */
    public function getContractFileUrlAttribute()
    {
        if ($this->contract_file) {
            return asset('storage/' . $this->contract_file);
        }
        return null;
    }

    /**
     * اسم نوع المؤسسة بالعربية
     */
    public function getTypeNameArAttribute()
    {
        return $this->type->name_ar ?? $this->type->name;
    }

    /**
     * اسم نوع المؤسسة بالإنجليزية
     */
    public function getTypeNameAttribute()
    {
        return $this->type->name;
    }

    /**
     * اسم المالك الأساسي
     */
    public function getPrimaryOwnerNameAttribute()
    {
        $owner = $this->primaryOwner();
        return $owner ? $owner->full_name : ($this->owner ? $this->owner->full_name : 'غير محدد');
    }

    /**
     * معرف المالك الأساسي
     */
    public function getPrimaryOwnerIdAttribute()
    {
        $owner = $this->primaryOwner();
        return $owner ? $owner->id : ($this->owner ? $this->owner->id : null);
    }

    /**
     * حالة الاتفاقية
     */
    public function getAgreementStatusAttribute()
    {
        if (!$this->agreement_expiry_date) {
            return 'active';
        }
        
        $expiryDate = $this->agreement_expiry_date instanceof Carbon 
            ? $this->agreement_expiry_date 
            : Carbon::parse($this->agreement_expiry_date);
        
        if ($expiryDate < now()) {
            return 'expired';
        }
        
        if ($expiryDate <= now()->addDays(30)) {
            return 'expiring_soon';
        }
        
        return 'active';
    }

    /**
     * إجمالي عدد الخصومات المقدمة
     */
    public function getTotalDiscountsGivenAttribute()
    {
        return $this->discountTransactions()->count();
    }

    /**
     * إجمالي قيمة التوفير للعملاء
     */
    public function getTotalSavingsGivenAttribute()
    {
        return (float) $this->discountTransactions()->sum('amount_saved');
    }

    /**
     * متوسط الخصم المقدم
     */
    public function getAverageDiscountAttribute()
    {
        return (float) ($this->discountTransactions()->avg('discount_percentage') ?? 0);
    }

    // ==================== Mutators ====================

    /**
     * تعيين نسبة الخصم
     */
    public function setDiscountPercentageAttribute($value): void
    {
        $this->attributes['discount_percentage'] = number_format((float) $value, 2, '.', '');
    }

    // ==================== Business Methods ====================

    /**
     * التحقق من صحة المؤسسة
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        
        if ($this->agreement_expiry_date) {
            $expiryDate = $this->agreement_expiry_date instanceof Carbon 
                ? $this->agreement_expiry_date 
                : Carbon::parse($this->agreement_expiry_date);
            
            if ($expiryDate < now()) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * تجديد الاتفاقية
     */
    public function renewAgreement($months = 12): self
    {
        if ($this->agreement_expiry_date && $this->agreement_expiry_date > now()) {
            $newDate = $this->agreement_expiry_date->copy()->addMonths($months);
            $this->agreement_expiry_date = $newDate;
        } else {
            $this->agreement_expiry_date = now()->addMonths($months);
        }
        
        $this->status = 'active';
        $this->save();
        
        return $this;
    }

    /**
     * تحديث نسبة الخصم
     */
    public function updateDiscountPercentage($percentage): self
    {
        $this->update(['discount_percentage' => number_format((float) $percentage, 2, '.', '')]);
        return $this;
    }

    /**
     * زيادة نسبة الخصم
     */
    public function increaseDiscountPercentage($percentage): self
    {
        $currentValue = (float) ($this->discount_percentage ?? 0);
        $newValue = $currentValue + (float) $percentage;
        $this->update(['discount_percentage' => number_format($newValue, 2, '.', '')]);
        return $this;
    }

    /**
     * إضافة مالك للمؤسسة
     */
    public function addOwner(User $user, $isPrimary = false): self
    {
        $this->owners()->attach($user->id, ['is_primary' => $isPrimary]);
        
        if ($isPrimary) {
            $this->owner_id = $user->id;
            $this->save();
        }
        
        return $this;
    }

    /**
     * إزالة مالك من المؤسسة
     */
    public function removeOwner(User $user): self
    {
        $this->owners()->detach($user->id);
        
        if ($this->owner_id === $user->id) {
            $newPrimary = $this->owners()->wherePivot('is_primary', true)->first();
            $this->owner_id = $newPrimary ? $newPrimary->id : null;
            $this->save();
        }
        
        return $this;
    }

    /**
     * تفعيل المؤسسة
     */
    public function activate(): self
    {
        $this->status = 'active';
        $this->save();
        return $this;
    }

    /**
     * تعطيل المؤسسة
     */
    public function deactivate(): self
    {
        $this->status = 'inactive';
        $this->save();
        return $this;
    }

    /**
     * التحقق من وجود مالك محدد
     */
    public function hasOwner(User $user): bool
    {
        return $this->owners()->where('user_id', $user->id)->exists();
    }

    /**
     * الحصول على جميع المالكين بأسمائهم
     */
    public function getOwnersNamesAttribute(): string
    {
        return $this->owners->pluck('full_name')->implode(', ');
    }
}