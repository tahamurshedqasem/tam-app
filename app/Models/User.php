<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'full_name',
        'phone',
        'password',
        'region',
        'email',
        'role',
        'status',
        'phone_verified_at',
        'device_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // ==================== Relationships ====================

    /**
     * علاقة العميل (للأدوار: customer)
     */
    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * علاقة المؤسسات التي يملكها (للدور: institution_owner)
     */
    public function institutionsOwned()
    {
        return $this->belongsToMany(Institution::class, 'institution_owners')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    /**
     * المؤسسة الأساسية التي يملكها
     */
    public function primaryInstitution()
    {
        return $this->belongsToMany(Institution::class, 'institution_owners')
                    ->wherePivot('is_primary', true)
                    ->first();
    }

    /**
     * العملاء الذين تم إنشاؤهم بواسطة هذا المسوق (للدور: customer_marketer)
     */
    public function createdCustomers()
    {
        return $this->hasMany(Customer::class, 'created_by_marketer');
    }

    /**
     * المؤسسات التي تم إنشاؤها بواسطة هذا المسوق (للدور: institution_marketer)
     */
    public function createdInstitutions()
    {
        return $this->hasMany(Institution::class, 'created_by_marketer');
    }

    /**
     * العمولات المستحقة لهذا المستخدم
     */
    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    /**
     * الإشعارات الخاصة بهذا المستخدم
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * سجلات النشاط الخاصة بهذا المستخدم
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * معاملات الخصم التي تمت بواسطة مالك المؤسسة
     */
    public function discountTransactionsAsOwner()
    {
        return $this->hasMany(DiscountTransaction::class, 'institution_owner_id');
    }

    // ==================== Scopes ====================

    /**
     * فلتر المستخدمين النشطين
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * فلتر حسب الدور
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * فلتر حسب حالة التوثيق
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('phone_verified_at');
    }

    // ==================== Accessors & Mutators ====================

    /**
     * الحصول على اسم المستخدم منسق
     */
    public function getFormattedNameAttribute()
    {
        return $this->full_name;
    }

    /**
     * التحقق من أن رقم الهاتف موثق
     */
    public function getIsVerifiedAttribute()
    {
        return !is_null($this->phone_verified_at);
    }

    // ==================== Role Check Methods ====================

    /**
     * التحقق من أن المستخدم أدمن
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * التحقق من أن المستخدم عميل
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * التحقق من أن المستخدم مسوق عملاء
     */
    public function isCustomerMarketer(): bool
    {
        return $this->role === 'customer_marketer';
    }

    /**
     * التحقق من أن المستخدم مسوق مؤسسات
     */
    public function isInstitutionMarketer(): bool
    {
        return $this->role === 'institution_marketer';
    }

    /**
     * التحقق من أن المستخدم مالك مؤسسة
     */
    public function isInstitutionOwner(): bool
    {
        return $this->role === 'institution_owner';
    }

    /**
     * التحقق من أن المستخدم مسوق (أي نوع)
     */
    public function isMarketer(): bool
    {
        return in_array($this->role, ['customer_marketer', 'institution_marketer']);
    }

    // ==================== Business Methods ====================

    /**
     * حساب إجمالي العمولات المستحقة
     */
    public function getTotalCommissionsAttribute()
    {
        return $this->commissions()
                    ->where('status', 'pending')
                    ->sum('amount');
    }

    /**
     * حساب إجمالي العمولات المدفوعة
     */
    public function getPaidCommissionsAttribute()
    {
        return $this->commissions()
                    ->where('status', 'paid')
                    ->sum('amount');
    }

    /**
     * الحصول على عدد العملاء الذين سجلهم (للمسوقين)
     */
    public function getRegisteredCustomersCountAttribute()
    {
        if (!$this->isCustomerMarketer()) {
            return 0;
        }
        return $this->createdCustomers()->count();
    }

    /**
     * الحصول على عدد المؤسسات التي سجلها (لمسوقي المؤسسات)
     */
    public function getRegisteredInstitutionsCountAttribute()
    {
        if (!$this->isInstitutionMarketer()) {
            return 0;
        }
        return $this->createdInstitutions()->count();
    }

    /**
     * توثيق رقم الهاتف
     */
    public function markPhoneAsVerified()
    {
        $this->phone_verified_at = now();
        $this->save();
    }

    
}