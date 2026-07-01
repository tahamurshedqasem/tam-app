<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Institution extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'institutions';

    protected $fillable = [
        'name',
        'type_id',
        'governorate_id',
        'district_id',
        'governorate_name',
        'district_name',
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
        'deleted_at' => 'datetime',
        'discount_percentage' => 'decimal:2'
    ];

    // ==================== Relationships ====================

    /**
     * نوع المؤسسة
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(InstitutionType::class, 'type_id');
    }

    /**
     * المحافظة
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * المنطقة
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * المالك الأساسي للمؤسسة
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * المسوق الذي قام بتسجيل هذه المؤسسة
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_marketer');
    }

    /**
     * جميع مالكي المؤسسة
     */
    public function owners(): BelongsToMany
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
    public function discountTransactions(): HasMany
    {
        return $this->hasMany(DiscountTransaction::class, 'institution_id');
    }

    /**
     * العمولات المتعلقة بهذه المؤسسة
     */
    public function commissions(): HasManyThrough
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
     * فلتر المؤسسات حسب المحافظة
     */
    public function scopeByGovernorate($query, $governorateId)
    {
        return $query->where('governorate_id', $governorateId);
    }

    /**
     * فلتر المؤسسات حسب المحافظة بالاسم
     */
    public function scopeByGovernorateName($query, $governorateName)
    {
        return $query->where('governorate_name', $governorateName);
    }

    /**
     * فلتر المؤسسات حسب المنطقة
     */
    public function scopeByDistrict($query, $districtId)
    {
        return $query->where('district_id', $districtId);
    }

    /**
     * فلتر المؤسسات حسب المنطقة بالاسم
     */
    public function scopeByDistrictName($query, $districtName)
    {
        return $query->where('district_name', $districtName);
    }

    /**
     * فلتر المؤسسات حسب المسوق
     */
    public function scopeByMarketer($query, $marketerId)
    {
        return $query->where('created_by_marketer', $marketerId);
    }

    /**
     * فلتر المؤسسات حسب المالك
     */
    public function scopeByOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
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

    /**
     * فلتر المؤسسات المنتهية الاتفاقية
     */
    public function scopeExpired($query)
    {
        return $query->where('agreement_expiry_date', '<', now());
    }

    /**
     * فلتر المؤسسات حسب البحث (الاسم، الهاتف، العنوان، المحافظة، المنطقة)
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%")
              ->orWhere('address', 'LIKE', "%{$search}%")
              ->orWhere('governorate_name', 'LIKE', "%{$search}%")
              ->orWhere('district_name', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }

    // ==================== Accessors ====================

    /**
     * الحصول على اسم المحافظة (مع دعم الـ Fallback)
     */
    public function getGovernorateDisplayAttribute(): string
    {
        return $this->governorate_name 
            ?? ($this->governorate?->name_ar ?? $this->governorate?->name ?? 'غير محدد');
    }

    /**
     * الحصول على اسم المنطقة (مع دعم الـ Fallback)
     */
    public function getDistrictDisplayAttribute(): string
    {
        return $this->district_name 
            ?? ($this->district?->name_ar ?? $this->district?->name ?? 'غير محدد');
    }

    /**
     * الحصول على الموقع الكامل (المحافظة - المنطقة)
     */
    public function getLocationFullAttribute(): string
    {
        $parts = [];
        
        if ($this->governorate_display && $this->governorate_display !== 'غير محدد') {
            $parts[] = $this->governorate_display;
        }
        
        if ($this->district_display && $this->district_display !== 'غير محدد') {
            $parts[] = $this->district_display;
        }
        
        return !empty($parts) ? implode(' - ', $parts) : 'غير محدد';
    }

    /**
     * الحصول على نسبة الخصم كـ float
     */
    public function getDiscountPercentageAttribute($value): float
    {
        return (float) ($value ?? 0);
    }

    /**
     * رابط ملف العقد
     */
    public function getContractFileUrlAttribute(): ?string
    {
        if ($this->contract_file) {
            return asset('storage/' . $this->contract_file);
        }
        return null;
    }

    /**
     * اسم نوع المؤسسة بالعربية
     */
    public function getTypeNameArAttribute(): string
    {
        return $this->type?->name_ar ?? $this->type?->name ?? 'غير محدد';
    }

    /**
     * اسم نوع المؤسسة بالإنجليزية
     */
    public function getTypeNameAttribute(): string
    {
        return $this->type?->name ?? 'غير محدد';
    }

    /**
     * اسم المالك الأساسي
     */
    public function getPrimaryOwnerNameAttribute(): string
    {
        $owner = $this->primaryOwner();
        return $owner ? $owner->full_name : ($this->owner ? $this->owner->full_name : 'غير محدد');
    }

    /**
     * معرف المالك الأساسي
     */
    public function getPrimaryOwnerIdAttribute(): ?int
    {
        $owner = $this->primaryOwner();
        return $owner ? $owner->id : ($this->owner ? $this->owner->id : null);
    }

    /**
     * حالة الاتفاقية
     */
    public function getAgreementStatusAttribute(): string
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
     * أيام متبقية على انتهاء الاتفاقية
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->agreement_expiry_date) {
            return null;
        }
        
        $expiryDate = $this->agreement_expiry_date instanceof Carbon 
            ? $this->agreement_expiry_date 
            : Carbon::parse($this->agreement_expiry_date);
        
        return now()->diffInDays($expiryDate, false);
    }

    /**
     * إجمالي عدد الخصومات المقدمة
     */
    public function getTotalDiscountsGivenAttribute(): int
    {
        return $this->discountTransactions()->count();
    }

    /**
     * إجمالي قيمة التوفير للعملاء
     */
    public function getTotalSavingsGivenAttribute(): float
    {
        return (float) $this->discountTransactions()->sum('amount_saved');
    }

    /**
     * متوسط الخصم المقدم
     */
    public function getAverageDiscountAttribute(): float
    {
        return (float) ($this->discountTransactions()->avg('discount_percentage') ?? 0);
    }

    /**
     * هل المؤسسة لديها محافظة محددة؟
     */
    public function getHasGovernorateAttribute(): bool
    {
        return !empty($this->governorate_id) || !empty($this->governorate_name);
    }

    /**
     * هل المؤسسة لديها منطقة محددة؟
     */
    public function getHasDistrictAttribute(): bool
    {
        return !empty($this->district_id) || !empty($this->district_name);
    }

    // ==================== Mutators ====================

    /**
     * تعيين نسبة الخصم
     */
    public function setDiscountPercentageAttribute($value): void
    {
        $this->attributes['discount_percentage'] = number_format((float) $value, 2, '.', '');
    }

    /**
     * تعيين اسم المحافظة (مع تنظيف النص)
     */
    public function setGovernorateNameAttribute($value): void
    {
        $this->attributes['governorate_name'] = $value ? trim($value) : null;
    }

    /**
     * تعيين اسم المنطقة (مع تنظيف النص)
     */
    public function setDistrictNameAttribute($value): void
    {
        $this->attributes['district_name'] = $value ? trim($value) : null;
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
     * تحديث موقع المؤسسة (المحافظة والمنطقة)
     */
    public function updateLocation(?int $governorateId, ?int $districtId, ?string $governorateName = null, ?string $districtName = null): self
    {
        $data = [];
        
        if ($governorateId !== null) {
            $data['governorate_id'] = $governorateId;
            $governorate = Governorate::find($governorateId);
            if ($governorate) {
                $data['governorate_name'] = $governorate->name_ar ?? $governorate->name;
            }
        } elseif ($governorateName !== null) {
            $data['governorate_name'] = trim($governorateName);
            $governorate = Governorate::where('name_ar', $governorateName)
                ->orWhere('name', $governorateName)
                ->first();
            if ($governorate) {
                $data['governorate_id'] = $governorate->id;
            }
        }
        
        if ($districtId !== null) {
            $data['district_id'] = $districtId;
            $district = District::find($districtId);
            if ($district) {
                $data['district_name'] = $district->name_ar ?? $district->name;
            }
        } elseif ($districtName !== null) {
            $data['district_name'] = trim($districtName);
            if (isset($data['governorate_id'])) {
                $district = District::where('governorate_id', $data['governorate_id'])
                    ->where(function($q) use ($districtName) {
                        $q->where('name_ar', $districtName)
                          ->orWhere('name', $districtName);
                    })
                    ->first();
                if ($district) {
                    $data['district_id'] = $district->id;
                }
            }
        }
        
        if (!empty($data)) {
            $this->update($data);
        }
        
        return $this;
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

    /**
     * الحصول على معلومات الموقع كـ Array
     */
    public function getLocationArrayAttribute(): array
    {
        return [
            'governorate_id' => $this->governorate_id,
            'governorate_name' => $this->governorate_display,
            'district_id' => $this->district_id,
            'district_name' => $this->district_display,
            'full_location' => $this->location_full,
        ];
    }

    /**
     * تنسيق البيانات للـ API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->only(['id', 'name', 'name_ar']),
            'type_name' => $this->type_name_ar,
            'location' => $this->location_array,
            'governorate' => $this->governorate?->only(['id', 'name', 'name_ar']),
            'district' => $this->district?->only(['id', 'name', 'name_ar']),
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'discount_percentage' => $this->discount_percentage,
            'contract_file' => $this->contract_file_url,
            'agreement_date' => $this->agreement_date?->toDateString(),
            'agreement_expiry_date' => $this->agreement_expiry_date?->toDateString(),
            'agreement_status' => $this->agreement_status,
            'status' => $this->status,
            'description' => $this->description,
            'business_hours' => $this->business_hours,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'owner' => $this->owner?->only(['id', 'full_name', 'phone', 'email']),
            'primary_owner_name' => $this->primary_owner_name,
            'marketer' => $this->createdBy?->only(['id', 'full_name', 'phone']),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}