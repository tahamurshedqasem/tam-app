<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use InvalidArgumentException;

class DiscountTransaction extends Model
{
    use HasFactory;

    protected $table = 'discount_transactions';

    protected $fillable = [
        'customer_id',
        'institution_id',
        'institution_owner_id',
        'discount_percentage',
        'original_amount',
        'discounted_amount',
        'amount_saved',
        'transaction_receipt',
        'transaction_date',
        'notes',
        'verification_method'
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
        // تم إزالة جميع الحقول العشرية من casts
    ];

    // ==================== Relationships ====================

    /**
     * العميل الذي حصل على الخصم
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * المؤسسة التي قدمت الخصم
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * مالك المؤسسة الذي قام بتسجيل الخصم
     */
    public function institutionOwner()
    {
        return $this->belongsTo(User::class, 'institution_owner_id');
    }

    /**
     * العمولة المرتبطة بهذه المعاملة
     */
    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    // ==================== Scopes ====================

    /**
     * فلتر المعاملات في تاريخ محدد
     */
    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('transaction_date', $date);
    }

    /**
     * فلتر المعاملات في فترة زمنية
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * فلتر معاملات مؤسسة معينة
     */
    public function scopeForInstitution($query, $institutionId)
    {
        return $query->where('institution_id', $institutionId);
    }

    /**
     * فلتر معاملات عميل معين
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
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
     * الحصول على المبلغ الأصلي كـ float
     */
    public function getOriginalAmountAttribute($value)
    {
        return (float) ($value ?? 0);
    }

    /**
     * الحصول على المبلغ بعد الخصم كـ float
     */
    public function getDiscountedAmountAttribute($value)
    {
        return (float) ($value ?? 0);
    }

    /**
     * الحصول على قيمة التوفير كـ float
     */
    public function getAmountSavedAttribute($value)
    {
        return (float) ($value ?? 0);
    }

    /**
     * اسم العميل
     */
    public function getCustomerNameAttribute()
    {
        return $this->customer->full_name ?? null;
    }

    /**
     * اسم المؤسسة
     */
    public function getInstitutionNameAttribute()
    {
        return $this->institution->name ?? null;
    }

    /**
     * رابط إيصال المعاملة
     */
    public function getReceiptUrlAttribute()
    {
        if ($this->transaction_receipt) {
            return asset('storage/' . $this->transaction_receipt);
        }
        return null;
    }

    /**
     * حساب نسبة التوفير
     */
    public function getSavingsPercentageAttribute()
    {
        $originalAmount = (float) ($this->original_amount ?? 0);
        $amountSaved = (float) ($this->amount_saved ?? 0);
        
        if ($originalAmount > 0) {
            return ($amountSaved / $originalAmount) * 100;
        }
        return (float) ($this->discount_percentage ?? 0);
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
     * تعيين المبلغ الأصلي
     */
    public function setOriginalAmountAttribute($value): void
    {
        $this->attributes['original_amount'] = number_format((float) $value, 2, '.', '');
    }

    /**
     * تعيين المبلغ بعد الخصم
     */
    public function setDiscountedAmountAttribute($value): void
    {
        $this->attributes['discounted_amount'] = number_format((float) $value, 2, '.', '');
    }

    /**
     * تعيين قيمة التوفير
     */
    public function setAmountSavedAttribute($value): void
    {
        $this->attributes['amount_saved'] = number_format((float) $value, 2, '.', '');
    }

    /**
     * تعيين تاريخ المعاملة
     */
    public function setTransactionDateAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['transaction_date'] = null;
        } elseif ($value instanceof Carbon) {
            $this->attributes['transaction_date'] = $value;
        } elseif (is_string($value)) {
            $this->attributes['transaction_date'] = $value;
        } else {
            $this->attributes['transaction_date'] = $value;
        }
    }

    // ==================== Business Methods ====================

    /**
     * حساب قيمة الخصم والتوفير - الطريقة المصححة
     * 
     * @param float|int|string $originalAmount
     * @return self
     */
    public function calculateSavings($originalAmount): self
    {
        // تحويل القيم إلى float بشكل آمن
        $originalAmountFloat = (float) $originalAmount;
        $discountPercentageFloat = (float) ($this->discount_percentage ?? 0);
        
        // التحقق من صحة المدخلات
        if ($originalAmountFloat <= 0) {
            throw new InvalidArgumentException('Original amount must be greater than zero.');
        }
        
        if ($discountPercentageFloat < 0 || $discountPercentageFloat > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }
        
        // حساب المبلغ بعد الخصم
        $discountedAmountFloat = $originalAmountFloat * (1 - ($discountPercentageFloat / 100));
        $amountSavedFloat = $originalAmountFloat - $discountedAmountFloat;
        
        // تعيين القيم (ستتعامل الـ mutators مع التحويل)
        $this->original_amount = $originalAmountFloat;
        $this->discounted_amount = $discountedAmountFloat;
        $this->amount_saved = $amountSavedFloat;
        
        return $this;
    }

    /**
     * حساب الخصم باستخدام نسبة مئوية محددة
     * 
     * @param float|int|string $originalAmount
     * @param float|int|string|null $discountPercentage
     * @return self
     */
    public function calculateDiscount($originalAmount, $discountPercentage = null): self
    {
        $originalAmountFloat = (float) $originalAmount;
        $discountPercentageFloat = (float) ($discountPercentage ?? $this->discount_percentage ?? 0);
        
        if ($originalAmountFloat <= 0) {
            throw new InvalidArgumentException('Original amount must be greater than zero.');
        }
        
        if ($discountPercentageFloat < 0 || $discountPercentageFloat > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }
        
        // تعيين نسبة الخصم
        $this->discount_percentage = $discountPercentageFloat;
        
        // حساب القيم
        $discountAmount = $originalAmountFloat * ($discountPercentageFloat / 100);
        $discountedAmountFloat = $originalAmountFloat - $discountAmount;
        
        $this->original_amount = $originalAmountFloat;
        $this->discounted_amount = $discountedAmountFloat;
        $this->amount_saved = $discountAmount;
        
        return $this;
    }

    /**
     * تسجيل المعاملة - الطريقة المصححة
     * 
     * @return self
     */
    public function recordTransaction(): self
    {
        $this->transaction_date = now();
        $this->save();
        
        // تحديث إجمالي التوفير للعميل
        if ($this->customer && $this->amount_saved > 0) {
            $this->customer->addSavings((float) $this->amount_saved);
        }
        
        return $this;
    }

    /**
     * إنشاء معاملة خصم جديدة
     * 
     * @param Customer $customer
     * @param Institution $institution
     * @param User $owner
     * @param float|int|string $originalAmount
     * @param float|int|string|null $discountPercentage
     * @return self
     */
    public static function createDiscountTransaction(
        Customer $customer,
        Institution $institution,
        User $owner,
        $originalAmount,
        $discountPercentage = null
    ): self {
        $percentage = $discountPercentage ?? $institution->discount_percentage;
        
        $transaction = new self();
        $transaction->customer_id = $customer->id;
        $transaction->institution_id = $institution->id;
        $transaction->institution_owner_id = $owner->id;
        $transaction->discount_percentage = (float) $percentage;
        $transaction->verification_method = 'manual';
        
        $transaction->calculateSavings($originalAmount);
        
        return $transaction;
    }

    /**
     * إنشاء معاملة خصم من QR code
     * 
     * @param Customer $customer
     * @param Institution $institution
     * @param User $owner
     * @param float|int|string $originalAmount
     * @return self
     */
    public static function createQRDiscountTransaction(
        Customer $customer,
        Institution $institution,
        User $owner,
        $originalAmount
    ): self {
        $transaction = self::createDiscountTransaction($customer, $institution, $owner, $originalAmount);
        $transaction->verification_method = 'qr';
        
        return $transaction;
    }

    /**
     * الحصول على تاريخ المعاملة كـ Carbon
     */
    public function getTransactionDateCarbonAttribute(): ?Carbon
    {
        if (!$this->transaction_date) {
            return null;
        }
        
        return $this->transaction_date instanceof Carbon 
            ? $this->transaction_date 
            : Carbon::parse($this->transaction_date);
    }

    /**
     * التحقق من أن المعاملة صالحة
     */
    public function isValid(): bool
    {
        return $this->original_amount > 0 
            && $this->discount_percentage >= 0 
            && $this->discount_percentage <= 100;
    }
}