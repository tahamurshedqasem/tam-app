<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'user_id',
        'membership_number',
        'address',
        'identity_image',
        'personal_image',
        'fingerprint_data',
        'created_by_marketer',
        'membership_expiry_date',
        'total_discount_saved',
        'membership_status', // ✅ إضافة الحقل
        'status', // ✅ إضافة الحقل
    ];

    protected $casts = [
        'fingerprint_data' => 'array',
        'membership_expiry_date' => 'datetime',
        'total_discount_saved' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==================== Relationships ====================

    /**
     * العلاقة مع المستخدم (العميل)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ✅ العلاقة مع المسوق الذي أضاف العميل
     */
    public function marketer()
    {
        return $this->belongsTo(User::class, 'created_by_marketer');
    }

    /**
     * العلاقة مع المسوق (اسم بديل)
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_marketer');
    }

    /**
     * العلاقة مع معاملات الخصم
     */
    public function discountTransactions()
    {
        return $this->hasMany(DiscountTransaction::class);
    }

    /**
     * العلاقة مع الإشعارات
     */
    public function notifications()
    {
        return $this->hasManyThrough(Notification::class, User::class, 'id', 'user_id', 'user_id');
    }

    // ==================== Scopes ====================

    /**
     * نطاق: العملاء الذين تنتهي عضويتهم قريباً
     */
    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('membership_expiry_date', '<=', now()->addDays($days))
                     ->where('membership_expiry_date', '>', now());
    }

    /**
     * نطاق: العملاء منتهية عضويتهم
     */
    public function scopeExpired($query)
    {
        return $query->where('membership_expiry_date', '<', now());
    }

    /**
     * نطاق: العملاء النشطين
     */
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->where('membership_status', 'active')
              ->orWhere('status', 'active')
              ->orWhere(function($q2) {
                  $q2->whereNull('membership_expiry_date')
                     ->orWhere('membership_expiry_date', '>', now());
              });
        });
    }

    /**
     * ✅ نطاق: العملاء المعلقين
     */
    public function scopeSuspended($query)
    {
        return $query->where(function($q) {
            $q->where('membership_status', 'suspended')
              ->orWhere('status', 'suspended');
        });
    }

    /**
     * ✅ نطاق: العملاء في انتظار التفعيل
     */
    public function scopePending($query)
    {
        return $query->where(function($q) {
            $q->where('membership_status', 'pending')
              ->orWhere('status', 'pending');
        });
    }

    // ==================== Accessors ====================

    /**
     * الحصول على رابط صورة الهوية
     */
    public function getIdentityImageUrlAttribute()
    {
        if ($this->identity_image) {
            return asset('storage/' . $this->identity_image);
        }
        return null;
    }

    /**
     * الحصول على رابط الصورة الشخصية
     */
    public function getPersonalImageUrlAttribute()
    {
        if ($this->personal_image) {
            return asset('storage/' . $this->personal_image);
        }
        return null;
    }

    /**
     * الحصول على الاسم الكامل
     */
    public function getFullNameAttribute()
    {
        return $this->user->full_name ?? null;
    }

    /**
     * الحصول على رقم الهاتف
     */
    public function getPhoneAttribute()
    {
        return $this->user->phone ?? null;
    }

    /**
     * الحصول على البريد الإلكتروني
     */
    public function getEmailAttribute()
    {
        return $this->user->email ?? null;
    }

    /**
     * ✅ الحصول على حالة العضوية (محدث)
     */
    public function getMembershipStatusAttribute()
    {
        // إذا كان هناك قيمة مخزنة، استخدمها
        if (isset($this->attributes['membership_status']) && !empty($this->attributes['membership_status'])) {
            return $this->attributes['membership_status'];
        }
        
        // وإلا احسبها من تاريخ الانتهاء
        if (!$this->membership_expiry_date) {
            return 'active';
        }
        
        $expiryDate = $this->membership_expiry_date instanceof Carbon 
            ? $this->membership_expiry_date 
            : Carbon::parse($this->membership_expiry_date);
        
        if ($expiryDate < now()) {
            return 'expired';
        }
        
        if ($expiryDate <= now()->addDays(30)) {
            return 'expiring_soon';
        }
        
        return 'active';
    }

    /**
     * ✅ الحصول على حالة العميل (من العمود status)
     */
    public function getStatusAttribute()
    {
        return $this->attributes['status'] ?? $this->membership_status;
    }

    /**
     * الحصول على الأيام المتبقية
     */
    public function getDaysRemainingAttribute()
    {
        if (!$this->membership_expiry_date) {
            return null;
        }
        
        $expiryDate = $this->membership_expiry_date instanceof Carbon 
            ? $this->membership_expiry_date 
            : Carbon::parse($this->membership_expiry_date);
        
        if ($expiryDate < now()) {
            return 0;
        }
        
        return now()->diffInDays($expiryDate);
    }

    /**
     * الحصول على إجمالي التوفير
     */
    public function getTotalSavingsAttribute()
    {
        return (float) $this->discountTransactions()->sum('amount_saved');
    }

    /**
     * الحصول على عدد استخدامات الخصم
     */
    public function getTotalDiscountUsageAttribute()
    {
        return $this->discountTransactions()->count();
    }

    /**
     * الحصول على إجمالي التوفير المخزن
     */
    public function getTotalDiscountSavedAttribute($value)
    {
        return (float) ($value ?? 0);
    }

    // ==================== Mutators ====================

    /**
     * تعيين إجمالي التوفير
     */
    public function setTotalDiscountSavedAttribute($value): void
    {
        $this->attributes['total_discount_saved'] = (string) round((float) $value, 2);
    }

    /**
     * تعيين تاريخ انتهاء العضوية
     */
    public function setMembershipExpiryDateAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['membership_expiry_date'] = null;
        } elseif ($value instanceof Carbon) {
            $this->attributes['membership_expiry_date'] = $value;
        } elseif (is_string($value)) {
            $this->attributes['membership_expiry_date'] = $value;
        } else {
            $this->attributes['membership_expiry_date'] = $value;
        }
    }

    /**
     * ✅ تعيين حالة العضوية
     */
    public function setMembershipStatusAttribute($value): void
    {
        $this->attributes['membership_status'] = $value;
        // تحديث عمود status أيضاً للمتوافقية
        $this->attributes['status'] = $value;
    }

    /**
     * ✅ تعيين حالة العميل
     */
    public function setStatusAttribute($value): void
    {
        $this->attributes['status'] = $value;
        // تحديث عمود membership_status أيضاً للمتوافقية
        $this->attributes['membership_status'] = $value;
    }

    // ==================== Business Methods ====================

    /**
     * تجديد العضوية
     */
    public function renewMembership($months = 12): self
    {
        if ($this->membership_expiry_date && $this->membership_expiry_date > now()) {
            $newDate = $this->membership_expiry_date->copy()->addMonths($months);
            $this->membership_expiry_date = $newDate;
        } else {
            $this->membership_expiry_date = now()->addMonths($months);
        }
        
        $this->membership_status = 'active';
        $this->status = 'active';
        $this->save();
        return $this;
    }

    /**
     * إضافة توفير
     */
    public function addSavings($amount): self
    {
        $this->increment('total_discount_saved', (float) $amount);
        return $this;
    }

    /**
     * خصم من التوفير
     */
    public function deductSavings($amount): self
    {
        $this->decrement('total_discount_saved', (float) $amount);
        return $this;
    }

    /**
     * ✅ إعادة تعيين إجمالي التوفير
     */
    public function resetSavings(): self
    {
        $this->update(['total_discount_saved' => 0]);
        return $this;
    }

    /**
     * ✅ طريقة بديلة لإعادة تعيين التوفير
     */
    public function resetSavingsAlternative(): self
    {
        $this->total_discount_saved = 0;
        $this->save();
        return $this;
    }

    /**
     * التحقق من صلاحية العضوية
     */
    public function isValidMembership(): bool
    {
        if ($this->membership_status === 'suspended' || $this->status === 'suspended') {
            return false;
        }
        
        if ($this->membership_status === 'expired' || $this->status === 'expired') {
            return false;
        }
        
        if (!$this->membership_expiry_date) {
            return true;
        }
        
        $expiryDate = $this->membership_expiry_date instanceof Carbon 
            ? $this->membership_expiry_date 
            : Carbon::parse($this->membership_expiry_date);
        
        return $expiryDate >= now();
    }

    /**
     * الحصول على كائن Carbon لتاريخ انتهاء العضوية
     */
    public function getMembershipExpiryCarbonAttribute(): ?Carbon
    {
        if (!$this->membership_expiry_date) {
            return null;
        }
        
        return $this->membership_expiry_date instanceof Carbon 
            ? $this->membership_expiry_date 
            : Carbon::parse($this->membership_expiry_date);
    }

    /**
     * التحقق من وجود بصمة
     */
    public function hasFingerprint(): bool
    {
        return !is_null($this->fingerprint_data);
    }

    /**
     * تسجيل بصمة
     */
    public function registerFingerprint(array $fingerprintData): self
    {
        $this->fingerprint_data = $fingerprintData;
        $this->save();
        return $this;
    }

    /**
     * حذف البصمة
     */
    public function deleteFingerprint(): self
    {
        $this->fingerprint_data = null;
        $this->save();
        return $this;
    }

    /**
     * ✅ تحديث حالة العميل
     */
    public function updateStatus(string $status): self
    {
        $this->membership_status = $status;
        $this->status = $status;
        $this->save();
        return $this;
    }

    /**
     * ✅ الحصول على حالة العميل كنص عربي
     */
    public function getStatusTextAttribute(): string
    {
        $status = $this->membership_status ?? $this->status ?? 'pending';
        
        return match($status) {
            'active' => 'نشط',
            'pending' => 'قيد الانتظار',
            'suspended' => 'محظور',
            'expired' => 'منتهية',
            'expiring_soon' => 'تنتهي قريباً',
            default => 'غير معروف',
        };
    }

    /**
     * ✅ الحصول على لون الحالة
     */
    public function getStatusColorAttribute(): string
    {
        $status = $this->membership_status ?? $this->status ?? 'pending';
        
        return match($status) {
            'active' => 'green',
            'pending' => 'orange',
            'suspended' => 'red',
            'expired' => 'red',
            'expiring_soon' => 'orange',
            default => 'grey',
        };
    }

    /**
     * ✅ التحقق من أن العميل نشط
     */
    public function isActive(): bool
    {
        return $this->isValidMembership() && 
               ($this->membership_status === 'active' || $this->status === 'active');
    }

    /**
     * ✅ التحقق من أن العميل محظور
     */
    public function isSuspended(): bool
    {
        return $this->membership_status === 'suspended' || $this->status === 'suspended';
    }

    /**
     * ✅ التحقق من أن العميل في انتظار التفعيل
     */
    public function isPending(): bool
    {
        return $this->membership_status === 'pending' || $this->status === 'pending';
    }
}